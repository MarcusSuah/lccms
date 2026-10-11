<?php

namespace App\Services\Census;

use App\Enums\Census\BuildingType;
use App\Enums\Census\OccupancyStatus;
use App\Enums\Census\RelationshipToHead;
use App\Enums\Census\ResidenceType;
use App\Enums\Census\SchoolAttendance;
use App\Enums\Census\Sex;
use App\Enums\Census\Tenure;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Field rules for census records, shared by web forms and the offline
 * synchronisation API so both paths enforce the same data quality.
 */
class CensusRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function house(): array
    {
        return [
            'local_house_number' => ['nullable', 'string', 'max:30'],
            'building_type' => ['nullable', Rule::enum(BuildingType::class)],
            'tenure' => ['nullable', Rule::enum(Tenure::class)],
            'floors' => ['nullable', 'integer', 'between:1,60'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'gps_accuracy_m' => ['nullable', 'integer', 'between:0,10000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function houseVisit(): array
    {
        return [
            'occupancy_status' => ['required', Rule::enum(OccupancyStatus::class)],
            'visited_at' => ['nullable', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function household(): array
    {
        return [
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-\s]{5,40}$/'],
            'residence_type' => ['required', Rule::enum(ResidenceType::class)],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function occupant(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today', 'after:1890-01-01', 'required_without:age_years'],
            'age_years' => ['nullable', 'integer', 'between:0,130', 'required_without:date_of_birth'],
            'age_is_estimated' => ['boolean'],
            'sex' => ['required', Rule::enum(Sex::class)],
            'relationship_to_head' => ['required', Rule::enum(RelationshipToHead::class)],
            'usual_resident' => ['required', 'boolean'],
            'school_attendance' => ['nullable', Rule::enum(SchoolAttendance::class)],
        ];
    }

    /**
     * Cross-field data quality checks for an occupant.
     *
     * @param  array<string, mixed>  $data
     */
    public static function checkOccupant(Validator $validator, array $data, CarbonInterface $referenceDate): void
    {
        $age = null;

        if (! empty($data['date_of_birth'])) {
            $age = (int) Carbon::parse($data['date_of_birth'])->diffInYears($referenceDate);

            if (isset($data['age_years']) && $data['age_years'] !== null && abs($age - (int) $data['age_years']) > 1) {
                $validator->errors()->add('age_years', 'Age does not match the date of birth.');
            }
        } elseif (isset($data['age_years'])) {
            $age = (int) $data['age_years'];
        }

        if ($age !== null && ($data['relationship_to_head'] ?? null) === RelationshipToHead::Head->value && $age < 12) {
            $validator->errors()->add('relationship_to_head', 'A household head younger than 12 is unlikely. Check the age or relationship.');
        }

        if ($age !== null && $age < 3 && in_array($data['school_attendance'] ?? null, [SchoolAttendance::Currently->value, SchoolAttendance::Previously->value], true)) {
            $validator->errors()->add('school_attendance', 'School attendance is not plausible for a child under 3.');
        }
    }
}
