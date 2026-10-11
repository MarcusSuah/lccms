<?php

namespace App\Services\Imports;

use App\Enums\Geography\GeoLevel;
use App\Enums\Imports\ImportStatus;
use App\Enums\RecordStatus;
use App\Enums\VerificationStatus;
use App\Imports\SpreadsheetRows;
use App\Models\Geography\GeoArea;
use App\Models\Imports\ImportJob;
use App\Models\User;
use App\Services\Geography\DuplicateNameDetector;
use App\Services\Geography\GeoAreaResolver;
use App\Services\Geography\GeoScope;
use App\Services\Geography\ReferenceCodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Validates and commits bulk geography imports.
 *
 * Rows reference their parent by reference code, either an existing record
 * or a row earlier in the same file. Validation is repeated at commit time
 * because the registry may have changed since the preview.
 */
class GeographyImportProcessor
{
    public const MAX_ROWS = 5000;

    public const COLUMNS = ['level', 'reference_code', 'name', 'parent_reference_code', 'district_number', 'description', 'latitude', 'longitude', 'verification_status'];

    public function __construct(
        private GeoScope $geoScope,
        private GeoAreaResolver $resolver,
        private DuplicateNameDetector $duplicates,
        private ReferenceCodeGenerator $codes,
    ) {}

    public function validate(ImportJob $import, User $user): ImportJob
    {
        $analysis = $this->analyse($import, $user);

        DB::transaction(function () use ($import, $analysis): void {
            $import->rowErrors()->delete();
            $import->rowErrors()->createMany($analysis['issues']);

            $import->forceFill([
                'status' => $analysis['error_rows'] === 0 && $analysis['rows'] !== [] ? ImportStatus::Validated : ImportStatus::Invalid,
                'total_rows' => count($analysis['rows']),
                'invalid_rows' => $analysis['error_rows'],
                'valid_rows' => max(0, count($analysis['rows']) - $analysis['error_rows']),
                'preview' => array_slice($analysis['rows'], 0, 100),
                'validated_at' => now(),
            ])->save();
        });

        return $import;
    }

    public function commit(ImportJob $import, User $user): ImportJob
    {
        if ($import->status !== ImportStatus::Validated) {
            throw ValidationException::withMessages(['import' => 'Only a successfully validated import can be committed.']);
        }

        $analysis = $this->analyse($import, $user);

        if ($analysis['error_rows'] > 0) {
            $this->validate($import, $user);

            throw ValidationException::withMessages(['import' => 'The registry changed since validation and the file now has errors. Review them and upload a corrected file.']);
        }

        DB::transaction(function () use ($import, $analysis): void {
            /** @var array<string, GeoArea> $created */
            $created = [];

            $rows = $analysis['rows'];
            usort($rows, fn (array $a, array $b): int => [GeoLevel::from($a['level'])->depth(), $a['row']] <=> [GeoLevel::from($b['level'])->depth(), $b['row']]);

            foreach ($rows as $row) {
                $level = GeoLevel::from($row['level']);
                $class = $level->modelClass();

                /** @var GeoArea $area */
                $area = new $class;
                $parent = null;

                if ($level->parent() !== null) {
                    $parent = $created[$level->parent()->value.':'.$row['parent_reference_code']]
                        ?? $this->findByCode($level->parent(), $row['parent_reference_code']);
                    $area->forceFill($parent->pathAttributes());
                }

                $area->fill([
                    'reference_code' => $row['reference_code'] ?: $this->codes->nextChildCode($parent),
                    'name' => $row['name'],
                    'description' => $row['description'] ?: null,
                    'latitude' => $row['latitude'],
                    'longitude' => $row['longitude'],
                    'verification_status' => $row['verification_status'],
                    'status' => RecordStatus::Active,
                ]);

                if ($level === GeoLevel::District) {
                    $area->setAttribute('district_number', $row['district_number']);
                }

                $area->save();

                $created[$level->value.':'.$area->reference_code] = $area;
            }

            $import->forceFill([
                'status' => ImportStatus::Committed,
                'imported_rows' => count($created),
                'committed_at' => now(),
            ])->save();
        });

        return $import;
    }

    /**
     * @return array{rows: list<array<string, mixed>>, issues: list<array{row_number: int, column: string|null, message: string, severity: string}>, error_rows: int}
     */
    public function analyse(ImportJob $import, User $user): array
    {
        $sheets = Excel::toArray(new SpreadsheetRows, $import->stored_path, 'local');
        $raw = $sheets[0] ?? [];

        $issues = [];
        $rows = [];
        $errorRows = [];

        if (count($raw) > self::MAX_ROWS) {
            return [
                'rows' => [],
                'issues' => [['row_number' => 0, 'column' => null, 'message' => 'The file has more than '.self::MAX_ROWS.' rows. Split it into smaller files.', 'severity' => 'error']],
                'error_rows' => 1,
            ];
        }

        $missingColumns = array_diff(['level', 'name'], array_keys($raw[0] ?? []));

        if ($raw === [] || $missingColumns !== []) {
            return [
                'rows' => [],
                'issues' => [['row_number' => 1, 'column' => null, 'message' => 'The file is empty or the heading row is missing required columns (level, name). Use the template.', 'severity' => 'error']],
                'error_rows' => 1,
            ];
        }

        /** @var array<string, array{name: string, parent: string|null}> $fileAreas keyed "level:code" */
        $fileAreas = [];
        /** @var array<string, true> $fileNames keyed "level:parent:normalized" */
        $fileNames = [];
        /** @var array<string, true> $fileDistrictNumbers keyed "county:number" */
        $fileDistrictNumbers = [];
        /** @var array<string, string|null> $fileCountyOf keyed "level:code" => county reference */
        $fileCountyOf = [];

        foreach ($raw as $index => $input) {
            $rowNumber = $index + 2;
            $add = function (?string $column, string $message, string $severity = 'error') use (&$issues, &$errorRows, $rowNumber): void {
                $issues[] = ['row_number' => $rowNumber, 'column' => $column, 'message' => $message, 'severity' => $severity];

                if ($severity === 'error') {
                    $errorRows[$rowNumber] = true;
                }
            };

            $row = [
                'row' => $rowNumber,
                'level' => strtolower(trim((string) ($input['level'] ?? ''))),
                'reference_code' => strtoupper(trim((string) ($input['reference_code'] ?? ''))),
                'name' => trim((string) ($input['name'] ?? '')),
                'parent_reference_code' => strtoupper(trim((string) ($input['parent_reference_code'] ?? ''))),
                'district_number' => ($input['district_number'] ?? '') === '' || ($input['district_number'] ?? null) === null ? null : $input['district_number'],
                'description' => trim((string) ($input['description'] ?? '')),
                'latitude' => ($input['latitude'] ?? '') === '' || ($input['latitude'] ?? null) === null ? null : $input['latitude'],
                'longitude' => ($input['longitude'] ?? '') === '' || ($input['longitude'] ?? null) === null ? null : $input['longitude'],
                'verification_status' => strtolower(trim((string) ($input['verification_status'] ?? ''))) ?: VerificationStatus::Unverified->value,
            ];

            if ($row['level'] === '' && $row['name'] === '' && $row['reference_code'] === '' && $row['parent_reference_code'] === '') {
                continue;
            }

            $level = GeoLevel::tryFrom($row['level']);

            if ($level === null) {
                $add('level', 'Level must be one of: '.implode(', ', GeoLevel::values()).'.');
                $rows[] = $row;

                continue;
            }

            if ($row['name'] === '' || mb_strlen($row['name']) > 150) {
                $add('name', 'Name is required and must be at most 150 characters.');
            }

            if (VerificationStatus::tryFrom($row['verification_status']) === null) {
                $add('verification_status', 'Verification status must be one of: '.implode(', ', VerificationStatus::values()).'.');
            }

            if ($row['reference_code'] !== '') {
                if (! preg_match('/^[A-Z0-9][A-Z0-9-]{0,59}$/', $row['reference_code'])) {
                    $add('reference_code', 'Reference codes use capital letters, digits and hyphens only (max 60).');
                } elseif (isset($fileAreas[$level->value.':'.$row['reference_code']])) {
                    $add('reference_code', 'This reference code appears more than once in the file.');
                } elseif ($this->findByCode($level, $row['reference_code']) !== null) {
                    $add('reference_code', 'This reference code already exists in the registry.');
                }
            } elseif ($level === GeoLevel::County) {
                $add('reference_code', 'Counties require a reference code.');
            }

            if (($row['latitude'] === null) !== ($row['longitude'] === null)) {
                $add('latitude', 'Provide both latitude and longitude, or neither.');
            } elseif ($row['latitude'] !== null && (! is_numeric($row['latitude']) || abs((float) $row['latitude']) > 90 || ! is_numeric($row['longitude']) || abs((float) $row['longitude']) > 180)) {
                $add('latitude', 'Coordinates are out of range.');
            }

            $parentKey = null;
            $countyCode = $level === GeoLevel::County ? $row['reference_code'] : null;

            if ($level->parent() !== null) {
                $parentLevel = $level->parent();

                if ($row['parent_reference_code'] === '') {
                    $add('parent_reference_code', "A {$level->value} requires the reference code of its {$parentLevel->value}.");
                } elseif (isset($fileAreas[$parentLevel->value.':'.$row['parent_reference_code']])) {
                    $parentKey = 'file:'.$row['parent_reference_code'];
                    $countyCode = $fileCountyOf[$parentLevel->value.':'.$row['parent_reference_code']] ?? null;
                } else {
                    $parent = $this->findByCode($parentLevel, $row['parent_reference_code']);

                    if ($parent === null) {
                        $add('parent_reference_code', "No {$parentLevel->value} with reference {$row['parent_reference_code']} exists in the registry or earlier in this file.");
                    } elseif (! $this->geoScope->allowsPath($user, $this->resolver->fullPath($parent))) {
                        $add('parent_reference_code', 'This parent is outside your assigned areas.');
                    } else {
                        $parentKey = 'db:'.$parent->id;
                        $countyCode = 'db:'.$parent->pathAttributes()['county_id'];

                        $exact = $this->duplicates->similarSiblings($level, $parent->id, $row['name']);

                        foreach ($exact as $similar) {
                            $isExact = $similar->normalized_name === GeoArea::normalizeName($row['name']);
                            $add('name', ($isExact ? 'Already exists' : 'Possible duplicate of')." {$similar->name} ({$similar->reference_code}).", $isExact ? 'error' : 'warning');
                        }
                    }
                }
            } elseif (! $this->geoScope->for($user)->national) {
                $add('level', 'Only national administrators may import counties.');
            }

            if ($row['name'] !== '') {
                $nameKey = $level->value.':'.($parentKey ?? 'root').':'.GeoArea::normalizeName($row['name']);

                if (isset($fileNames[$nameKey])) {
                    $add('name', 'The same name appears twice under the same parent in this file.');
                }

                $fileNames[$nameKey] = true;

                if ($level === GeoLevel::County && GeoLevel::County->modelClass()::query()->where('normalized_name', GeoArea::normalizeName($row['name']))->exists()) {
                    $add('name', 'A county with this name already exists.');
                }
            }

            if ($level === GeoLevel::District && $row['district_number'] !== null) {
                if (! ctype_digit((string) $row['district_number']) || (int) $row['district_number'] < 1 || (int) $row['district_number'] > 999) {
                    $add('district_number', 'District number must be a whole number between 1 and 999.');
                } else {
                    $row['district_number'] = (int) $row['district_number'];
                    $numberKey = ($countyCode ?? '?').':'.$row['district_number'];

                    if (isset($fileDistrictNumbers[$numberKey])) {
                        $add('district_number', 'This district number is used twice for the same county in this file.');
                    } elseif ($parentKey !== null && str_starts_with($parentKey, 'db:')
                        && GeoLevel::District->modelClass()::query()->where('county_id', (int) substr($parentKey, 3))->where('district_number', $row['district_number'])->exists()) {
                        $add('district_number', 'This district number already exists in the county.');
                    }

                    $fileDistrictNumbers[$numberKey] = true;
                }
            }

            if ($row['reference_code'] !== '') {
                $fileAreas[$level->value.':'.$row['reference_code']] = ['name' => $row['name'], 'parent' => $parentKey];
                $fileCountyOf[$level->value.':'.$row['reference_code']] = $countyCode;
            }

            $row['has_error'] = isset($errorRows[$rowNumber]);
            $rows[] = $row;
        }

        return ['rows' => $rows, 'issues' => $issues, 'error_rows' => count($errorRows)];
    }

    private function findByCode(GeoLevel $level, string $code): ?GeoArea
    {
        /** @var GeoArea|null $area */
        $area = $level->modelClass()::query()->where('reference_code', $code)->first();

        return $area;
    }
}
