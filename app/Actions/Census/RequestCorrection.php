<?php

namespace App\Actions\Census;

use App\Enums\Access\Permission;
use App\Enums\Census\CensusRecordStatus;
use App\Enums\Census\CorrectionStatus;
use App\Exceptions\CensusActionException;
use App\Models\Census\Household;
use App\Models\Census\Occupant;
use App\Models\Census\RecordCorrection;
use App\Models\User;
use App\Services\Census\CensusAccess;
use App\Services\Census\CensusRules;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Proposes a change to a verified or approved record. The change is applied
 * only when a different authorised person approves it.
 */
class RequestCorrection
{
    public function __construct(private CensusAccess $access) {}

    /**
     * @param  array<string, mixed>  $changes  field => new value
     */
    public function handle(User $actor, Household|Occupant $record, array $changes, string $reason, string $source = 'web'): RecordCorrection
    {
        if (! $actor->can(Permission::CensusCorrect->value) || ! $this->access->canAccess($actor, $record)) {
            throw CensusActionException::unauthorized('You cannot request corrections in this area.');
        }

        $record->loadMissing('campaign');

        if ($record->campaign->status->isFinal()) {
            throw new CensusActionException(CensusActionException::CAMPAIGN_NOT_ACCEPTING, 'The campaign is closed. Its approved results are final.');
        }

        if (! in_array($record->status, [CensusRecordStatus::Verified, CensusRecordStatus::Approved], true)) {
            throw new CensusActionException(CensusActionException::INVALID_TRANSITION, 'Only verified or approved records use the correction workflow. Return the record to the enumerator instead.');
        }

        $rules = $record instanceof Occupant ? CensusRules::occupant() : CensusRules::household();
        $allowed = array_intersect_key($changes, $rules);

        if ($allowed === []) {
            throw ValidationException::withMessages(['changes' => 'Propose at least one change to a correctable field.']);
        }

        $merged = [...$this->currentValues($record, array_keys($rules)), ...$allowed];
        $validator = Validator::make($merged, $rules);

        if ($record instanceof Occupant) {
            $record->loadMissing('campaign');
            $validator->after(fn ($validator) => CensusRules::checkOccupant($validator, $merged, $record->campaign->reference_date));
        }

        $validator->validate();

        $diff = [];

        foreach ($allowed as $field => $value) {
            $current = $this->currentValues($record, [$field])[$field];

            if ((string) $current !== (string) $value) {
                $diff[$field] = ['from' => $current, 'to' => $value];
            }
        }

        if ($diff === []) {
            throw ValidationException::withMessages(['changes' => 'The proposed values are the same as the current record.']);
        }

        $hasPending = $record->corrections()->where('status', CorrectionStatus::Pending)->exists();

        if ($hasPending) {
            throw ValidationException::withMessages(['changes' => 'A correction for this record is already awaiting a decision.']);
        }

        return $record->corrections()->create([
            'campaign_id' => $record->campaign_id,
            'changes' => $diff,
            'reason' => $reason,
            'source' => $source,
            'requested_by' => $actor->id,
        ]);
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function currentValues(Household|Occupant $record, array $fields): array
    {
        $values = [];

        foreach ($fields as $field) {
            $value = $record->getAttribute($field);
            $values[$field] = match (true) {
                $value instanceof BackedEnum => $value->value,
                $value instanceof DateTimeInterface => $value->format('Y-m-d'),
                default => $value,
            };
        }

        return $values;
    }
}
