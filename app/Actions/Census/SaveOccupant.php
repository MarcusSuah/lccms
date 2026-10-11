<?php

namespace App\Actions\Census;

use App\Enums\Census\CensusRecordStatus;
use App\Models\Census\Household;
use App\Models\Census\Occupant;
use App\Models\User;
use App\Services\Census\CensusReferenceCodes;
use App\Services\Census\DuplicateDetector;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Adds or edits a person in a household. The household's editability rules
 * apply; the database guarantees at most one head per household.
 */
class SaveOccupant
{
    public function __construct(
        private SaveHousehold $households,
        private CensusReferenceCodes $codes,
        private DuplicateDetector $duplicates,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated with CensusRules::occupant()
     */
    public function create(User $actor, Household $household, array $data, ?string $uuid = null): Occupant
    {
        $this->households->assertFieldEditable($actor, $household);

        $occupant = DB::transaction(function () use ($actor, $household, $data, $uuid): Occupant {
            $occupant = new Occupant($data);
            $occupant->forceFill([
                ...$household->geoPath(),
                'uuid' => $uuid ?? (string) Str::uuid(),
                'reference_code' => $this->codes->forOccupant($household),
                'household_id' => $household->id,
                'campaign_id' => $household->campaign_id,
                'enumerator_id' => $actor->id,
                'status' => $household->status === CensusRecordStatus::Rejected ? CensusRecordStatus::Rejected : CensusRecordStatus::Draft,
            ]);

            $this->saveGuardingHead($occupant);

            return $occupant;
        });

        $this->duplicates->scan($occupant);

        return $occupant;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Occupant $occupant, array $data): Occupant
    {
        $occupant->loadMissing('household');
        $this->households->assertFieldEditable($actor, $occupant->household);

        $occupant->fill($data);
        $this->saveGuardingHead($occupant);

        $this->duplicates->scan($occupant);

        return $occupant;
    }

    private function saveGuardingHead(Occupant $occupant): void
    {
        try {
            $occupant->save();
        } catch (UniqueConstraintViolationException $exception) {
            if (str_contains($exception->getMessage(), 'occupants_one_head_per_household')) {
                throw ValidationException::withMessages([
                    'relationship_to_head' => 'This household already has a head. Change the existing head first.',
                ]);
            }

            throw $exception;
        }
    }
}
