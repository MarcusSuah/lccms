<?php

namespace App\Services\Reports\Definitions;

use App\Enums\Access\Permission;
use App\Enums\Census\CensusRecordStatus;
use App\Enums\Geography\GeoLevel;
use App\Models\Census\Occupant;
use App\Models\User;
use App\Services\Census\CensusStatistics;
use App\Services\Reports\ReportDefinition;
use Generator;

/**
 * Named list of occupants. This is personal data: viewing needs
 * census.view-personal-data and exporting additionally needs
 * reports.export-personal-data and a stated purpose, which is logged.
 */
class OccupantListReport extends ReportDefinition
{
    public function key(): string
    {
        return 'occupant-list';
    }

    public function title(): string
    {
        return 'Occupant list';
    }

    public function description(): string
    {
        return 'Named occupants with age, sex, relationship and household. Contains personal data; exports are logged with their purpose.';
    }

    public function category(): string
    {
        return 'Census';
    }

    public function authorize(User $user): bool
    {
        return $user->can(Permission::CensusViewPersonalData->value);
    }

    public function containsPersonalData(): bool
    {
        return true;
    }

    public function filters(): array
    {
        return [
            'campaign' => [],
            'area' => [],
            'status' => ['label' => 'Record status', 'options' => CensusRecordStatus::options()],
        ];
    }

    public function columns(array $filters): array
    {
        return [
            self::text('reference_code', 'Reference'),
            self::text('full_name', 'Full name'),
            self::text('sex', 'Sex'),
            self::number('age', 'Age'),
            self::text('age_is_estimated', 'Age estimated'),
            self::text('relationship', 'Relationship to head'),
            self::text('household', 'Household'),
            self::text('community', 'Community'),
            self::text('block', 'Block'),
            self::text('status', 'Status'),
        ];
    }

    /**
     * Streams rows in chunks so a large export never holds every record in memory.
     *
     * @return Generator<int, array<string, scalar|null>>
     */
    public function rows(User $user, array $filters): iterable
    {
        if ($filters['campaign'] === null) {
            return;
        }

        $campaign = $filters['campaign'];
        $communities = [];
        $blocks = [];

        $occupants = $this->wherePath(Occupant::query()->accessibleBy($user), $filters['area_path'])
            ->where('campaign_id', $campaign->id)
            ->when($filters['status'] === null, fn ($query) => $query->where('status', '!=', CensusRecordStatus::Archived))
            ->when($filters['status'] !== null, fn ($query) => $query->where('status', $filters['status']))
            ->select('*')
            ->selectRaw(CensusStatistics::AGE_AT_REFERENCE_DATE.' AS age_at_reference', [$campaign->reference_date->toDateString()])
            ->with('household:id,reference_code')
            ->lazyById(1000, 'occupants.id', 'id');

        foreach ($occupants as $occupant) {
            $communities += $this->missingNames(GeoLevel::Community, $occupant->community_id, $communities);
            $blocks += $this->missingNames(GeoLevel::Block, $occupant->block_id, $blocks);

            yield [
                'reference_code' => $occupant->reference_code,
                'full_name' => $occupant->full_name,
                'sex' => $occupant->sex?->label(),
                'age' => $occupant->age_at_reference === null ? null : (int) $occupant->age_at_reference,
                'age_is_estimated' => $occupant->age_is_estimated ? 'Yes' : 'No',
                'relationship' => $occupant->relationship_to_head?->label(),
                'household' => $occupant->household?->reference_code,
                'community' => $communities[$occupant->community_id] ?? null,
                'block' => $blocks[$occupant->block_id] ?? null,
                'status' => $occupant->status->label(),
            ];
        }
    }

    /**
     * @param  array<int, string>  $known
     * @return array<int, string>
     */
    private function missingNames(GeoLevel $level, ?int $id, array $known): array
    {
        if ($id === null || isset($known[$id])) {
            return [];
        }

        return $this->areaNames($level, [$id]);
    }
}
