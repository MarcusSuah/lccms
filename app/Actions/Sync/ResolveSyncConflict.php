<?php

namespace App\Actions\Sync;

use App\Actions\Census\RequestCorrection;
use App\Enums\Access\Permission;
use App\Enums\Sync\ConflictStatus;
use App\Exceptions\CensusActionException;
use App\Models\Census\House;
use App\Models\Census\HouseEnumeration;
use App\Models\Census\Household;
use App\Models\Census\Occupant;
use App\Models\Sync\SyncConflict;
use App\Models\User;
use App\Services\Census\CensusAccess;
use App\Services\Census\CensusRules;
use App\Services\Census\CensusStatistics;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Authorised resolution of a preserved offline conflict:
 *  - keep_server: the device change is discarded (it stays in the log),
 *  - apply_client: the device values replace the server values, only while
 *    the record is still editable,
 *  - convert_correction: for reviewed records, the device values become a
 *    correction request that a second person must approve.
 */
class ResolveSyncConflict
{
    public function __construct(
        private CensusAccess $access,
        private RequestCorrection $corrections,
    ) {}

    public function handle(User $resolver, SyncConflict $conflict, string $decision, string $note): SyncConflict
    {
        if (! $resolver->can(Permission::SyncResolveConflicts->value)) {
            throw CensusActionException::unauthorized('You cannot resolve synchronisation conflicts.');
        }

        if ($conflict->status !== ConflictStatus::Open) {
            throw ValidationException::withMessages(['decision' => 'This conflict has already been resolved.']);
        }

        $record = $this->record($conflict);

        if (! $this->access->canVerify($resolver, $record)) {
            throw CensusActionException::unauthorized('This record is outside the areas you supervise.');
        }

        return DB::transaction(function () use ($resolver, $conflict, $decision, $note, $record): SyncConflict {
            $status = match ($decision) {
                'keep_server' => ConflictStatus::KeptServer,
                'apply_client' => $this->applyClient($record, $conflict),
                'convert_correction' => $this->convertToCorrection($resolver, $record, $conflict, $note),
                default => throw ValidationException::withMessages(['decision' => 'Unknown decision.']),
            };

            $conflict->forceFill([
                'status' => $status,
                'resolved_by' => $resolver->id,
                'resolved_at' => now(),
                'resolution_note' => $note,
            ])->save();

            return $conflict;
        });
    }

    private function applyClient(Model $record, SyncConflict $conflict): ConflictStatus
    {
        $status = $record->getAttribute('status');

        if ($status !== null && method_exists($status, 'isEditableInField') && ! $status->isEditableInField()) {
            throw ValidationException::withMessages(['decision' => 'The record has been reviewed. Convert the change into a correction request instead.']);
        }

        $rules = match (true) {
            $record instanceof House => CensusRules::house(),
            $record instanceof HouseEnumeration => CensusRules::houseVisit(),
            $record instanceof Household => CensusRules::household(),
            $record instanceof Occupant => CensusRules::occupant(),
        };

        $data = Validator::make(array_intersect_key($conflict->submitted_payload, $rules), array_map(
            fn (array $fieldRules): array => array_values(array_filter($fieldRules, fn ($rule): bool => ! is_string($rule) || ! str_starts_with($rule, 'required'))),
            $rules,
        ))->validate();

        $record->fill($data)->save();

        if ($record->getAttribute('campaign_id') !== null) {
            CensusStatistics::invalidate((int) $record->getAttribute('campaign_id'));
        }

        return ConflictStatus::AppliedClient;
    }

    private function convertToCorrection(User $resolver, Model $record, SyncConflict $conflict, string $note): ConflictStatus
    {
        if (! $record instanceof Household && ! $record instanceof Occupant) {
            throw ValidationException::withMessages(['decision' => 'Only household and occupant changes can become corrections.']);
        }

        $this->corrections->handle($resolver, $record, $conflict->submitted_payload, $note, 'sync');

        return ConflictStatus::ConvertedToCorrection;
    }

    private function record(SyncConflict $conflict): Model
    {
        $class = match ($conflict->entity_type) {
            'house' => House::class,
            'visit' => HouseEnumeration::class,
            'household' => Household::class,
            'occupant' => Occupant::class,
        };

        return $class::query()->findOrFail($conflict->entity_id);
    }
}
