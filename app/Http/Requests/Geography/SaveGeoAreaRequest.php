<?php

namespace App\Http\Requests\Geography;

use App\Enums\Geography\GeoLevel;
use App\Enums\RecordStatus;
use App\Enums\VerificationStatus;
use App\Models\Geography\GeoArea;
use App\Services\Geography\DuplicateNameDetector;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a geographic area for creation or update. The parent can only be
 * chosen at creation; moving an area uses the audited reassignment workflow.
 */
class SaveGeoAreaRequest extends FormRequest
{
    public function level(): GeoLevel
    {
        $level = $this->route('level');

        return $level instanceof GeoLevel ? $level : GeoLevel::from((string) $level);
    }

    public function existingArea(): ?GeoArea
    {
        $area = $this->route('geoArea');

        return $area instanceof GeoArea ? $area : null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $level = $this->level();
        $existing = $this->existingArea();
        $parentLevel = $level->parent();

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'reference_code' => [
                $level === GeoLevel::County && $existing === null ? 'required' : 'nullable',
                'string', 'max:60', 'regex:/^[A-Z0-9][A-Z0-9-]*$/',
                Rule::unique($level->table(), 'reference_code')->ignore($existing?->id),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::enum(RecordStatus::class)],
            'verification_status' => ['required', Rule::enum(VerificationStatus::class)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'boundary' => ['nullable', 'json', 'max:2000000'],
            'confirm_possible_duplicate' => ['sometimes', 'boolean'],
        ];

        if ($parentLevel !== null && $existing === null) {
            $rules['parent_id'] = ['required', 'integer', Rule::exists($parentLevel->table(), 'id')];
        }

        if ($level === GeoLevel::District) {
            $countyId = $existing?->county_id ?? $this->integer('parent_id');

            $rules['district_number'] = [
                'nullable', 'integer', 'between:1,999',
                Rule::unique('districts', 'district_number')->where('county_id', $countyId)->ignore($existing?->id),
            ];
        }

        if ($level === GeoLevel::Community) {
            $rules['history'] = ['nullable', 'string', 'max:20000'];
            $rules['public_email'] = ['nullable', 'email', 'max:255'];
            $rules['public_phone'] = ['nullable', 'string', 'max:40'];
            $rules['is_published'] = ['sometimes', 'boolean'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reference_code.regex' => 'Use capital letters, digits and hyphens only.',
            'district_number.unique' => 'This district number is already used in the county.',
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $level = $this->level();
                $existing = $this->existingArea();
                $parentId = $existing?->getAttribute($level->parent()?->column() ?? '') ?? $this->integer('parent_id');
                $parentId = $level->parent() === null ? null : (int) $parentId;

                $similar = app(DuplicateNameDetector::class)->similarSiblings(
                    $level, $parentId, (string) $this->input('name'), $existing?->id,
                );

                $exact = $similar->first(fn (GeoArea $area): bool => $area->normalized_name === GeoArea::normalizeName((string) $this->input('name')));

                if ($exact !== null) {
                    $validator->errors()->add('name', "A {$level->value} with this name already exists here ({$exact->reference_code}).");

                    return;
                }

                if ($similar->isNotEmpty() && ! $this->boolean('confirm_possible_duplicate')) {
                    $names = $similar->map(fn (GeoArea $area): string => "{$area->name} ({$area->reference_code})")->join(', ');
                    $validator->errors()->add('name', "Possible duplicate of: {$names}. Confirm to create it anyway.");
                }

                if ($this->filled('boundary') && ! $this->isPolygonGeoJson((string) $this->input('boundary'))) {
                    $validator->errors()->add('boundary', 'Boundary must be a GeoJSON Polygon or MultiPolygon.');
                }
            },
        ];
    }

    private function isPolygonGeoJson(string $json): bool
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return false;
        }

        if (($decoded['type'] ?? null) === 'Feature') {
            $decoded = $decoded['geometry'] ?? null;
        }

        return is_array($decoded)
            && in_array($decoded['type'] ?? null, ['Polygon', 'MultiPolygon'], true)
            && is_array($decoded['coordinates'] ?? null);
    }
}
