<?php

namespace App\Services\Facilities;

use App\Enums\Facilities\FacilityCategory;
use Illuminate\Validation\Rule;

/**
 * Category-specific attributes stored in facilities.details. Only these keys
 * are kept; anything else submitted is discarded.
 */
class FacilityDetailRules
{
    public const EDUCATION_LEVELS = ['early_childhood', 'primary', 'junior_high', 'senior_high', 'vocational', 'tertiary'];

    public const HEALTH_SERVICES = ['outpatient', 'inpatient', 'maternity', 'immunization', 'laboratory', 'pharmacy', 'emergency', 'dental', 'mental_health', 'family_planning'];

    public const WEEKDAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /**
     * @return array<string, list<mixed>>
     */
    public static function for(FacilityCategory $category): array
    {
        return match ($category) {
            FacilityCategory::School => [
                'details.education_levels' => ['nullable', 'array'],
                'details.education_levels.*' => [Rule::in(self::EDUCATION_LEVELS)],
                'details.classrooms' => ['nullable', 'integer', 'between:0,1000'],
                'details.enrolment_total' => ['nullable', 'integer', 'between:0,100000'],
                'details.enrolment_female' => ['nullable', 'integer', 'between:0,100000', 'lte:details.enrolment_total'],
                'details.teachers' => ['nullable', 'integer', 'between:0,5000'],
            ],
            FacilityCategory::Hospital, FacilityCategory::Clinic, FacilityCategory::OtherHealth => [
                'details.services' => ['nullable', 'array'],
                'details.services.*' => [Rule::in(self::HEALTH_SERVICES)],
                'details.beds' => ['nullable', 'integer', 'between:0,5000'],
                'details.staff' => ['nullable', 'integer', 'between:0,10000'],
            ],
            FacilityCategory::Market => [
                'details.operating_days' => ['nullable', 'array'],
                'details.operating_days.*' => [Rule::in(self::WEEKDAYS)],
                'details.stalls' => ['nullable', 'integer', 'between:0,100000'],
                'details.condition' => ['nullable', Rule::in(['good', 'fair', 'poor'])],
            ],
            FacilityCategory::Sanitation => [
                'details.stalls' => ['nullable', 'integer', 'between:0,1000'],
                'details.functional_stalls' => ['nullable', 'integer', 'between:0,1000', 'lte:details.stalls'],
                'details.accessible' => ['nullable', 'boolean'],
                'details.maintenance_status' => ['nullable', Rule::in(['maintained', 'irregular', 'not_maintained'])],
                'details.fee_charged' => ['nullable', 'boolean'],
            ],
            FacilityCategory::OtherService => [],
        };
    }

    /**
     * @param  array<string, mixed>|null  $details
     * @return array<string, mixed>|null
     */
    public static function only(FacilityCategory $category, ?array $details): ?array
    {
        if ($details === null) {
            return null;
        }

        $keys = array_unique(array_map(
            fn (string $key): string => explode('.', $key)[1],
            array_keys(self::for($category)),
        ));

        $kept = array_intersect_key($details, array_flip($keys));

        return $kept === [] ? null : $kept;
    }
}
