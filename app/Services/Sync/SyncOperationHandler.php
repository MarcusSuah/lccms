<?php

namespace App\Services\Sync;

use App\Actions\Census\CensusWorkflow;
use App\Actions\Census\RecordHouseVisit;
use App\Actions\Census\RegisterHouse;
use App\Actions\Census\SaveHousehold;
use App\Actions\Census\SaveOccupant;
use App\Enums\Census\CensusRecordStatus;
use App\Enums\Sync\ConflictClass;
use App\Exceptions\CensusActionException;
use App\Models\Census\CensusCampaign;
use App\Models\Census\House;
use App\Models\Census\HouseEnumeration;
use App\Models\Census\Household;
use App\Models\Census\Occupant;
use App\Models\Geography\Block;
use App\Models\User;
use App\Services\Census\CensusAccess;
use App\Services\Census\CensusRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Applies one validated device operation to the census through the same
 * actions the web uses, so every rule (assignment, ownership, workflow,
 * one head per household) is enforced identically.
 */
class SyncOperationHandler
{
    /**
     * entity type => allowed operations
     *
     * @var array<string, list<string>>
     */
    public const OPERATIONS = [
        'house' => ['create', 'update'],
        'visit' => ['upsert'],
        'household' => ['create', 'update', 'submit'],
        'occupant' => ['create', 'update', 'archive'],
    ];

    public function __construct(
        private CensusAccess $access,
        private RegisterHouse $houses,
        private RecordHouseVisit $visits,
        private SaveHousehold $households,
        private SaveOccupant $occupants,
        private CensusWorkflow $workflow,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed> record summary returned to the device
     */
    public function apply(User $user, CensusCampaign $campaign, string $entityType, string $operation, string $entityUuid, ?int $baseVersion, array $payload): array
    {
        return match ("{$entityType}.{$operation}") {
            'house.create' => $this->createHouse($user, $campaign, $entityUuid, $payload),
            'house.update' => $this->updateHouse($user, $campaign, $entityUuid, $baseVersion, $payload),
            'visit.upsert' => $this->upsertVisit($user, $campaign, $entityUuid, $baseVersion, $payload),
            'household.create' => $this->createHousehold($user, $campaign, $entityUuid, $payload),
            'household.update' => $this->updateHousehold($user, $campaign, $entityUuid, $baseVersion, $payload),
            'household.submit' => $this->submitHousehold($user, $campaign, $entityUuid, $baseVersion),
            'occupant.create' => $this->createOccupant($user, $campaign, $entityUuid, $payload),
            'occupant.update' => $this->updateOccupant($user, $campaign, $entityUuid, $baseVersion, $payload),
            'occupant.archive' => $this->archiveOccupant($user, $campaign, $entityUuid, $baseVersion),
        };
    }

    /**
     * Find an entity this operation targets, for entity-level idempotency.
     */
    public function existing(string $entityType, string $entityUuid): ?Model
    {
        return match ($entityType) {
            'house' => House::query()->where('uuid', $entityUuid)->first(),
            'visit' => HouseEnumeration::query()->where('uuid', $entityUuid)->first(),
            'household' => Household::query()->where('uuid', $entityUuid)->first(),
            'occupant' => Occupant::query()->where('uuid', $entityUuid)->first(),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function summarize(Model $record): array
    {
        return array_filter([
            'id' => $record->getKey(),
            'uuid' => $record->getAttribute('uuid'),
            'reference_code' => $record->getAttribute('reference_code'),
            'version' => $record->getAttribute('version'),
            'status' => $record->getAttribute('status')?->value,
        ], fn ($value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function createHouse(User $user, CensusCampaign $campaign, string $uuid, array $payload): array
    {
        $data = $this->validate($payload, ['block_id' => ['required', 'integer'], ...CensusRules::house()]);
        $block = Block::query()->find($data['block_id']) ?? throw ValidationException::withMessages(['block_id' => 'Unknown block.']);

        return self::summarize($this->houses->create($user, $campaign, $block, Arr::except($data, ['block_id']), $uuid));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function updateHouse(User $user, CensusCampaign $campaign, string $uuid, ?int $baseVersion, array $payload): array
    {
        $house = House::query()->where('uuid', $uuid)->lockForUpdate()->firstOrFail();
        $this->assertVersion($house, $baseVersion, editable: true);
        $data = $this->validate($payload, CensusRules::house());

        return self::summarize($this->houses->update($user, $campaign, $house, $data));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function upsertVisit(User $user, CensusCampaign $campaign, string $uuid, ?int $baseVersion, array $payload): array
    {
        $existing = HouseEnumeration::query()->where('uuid', $uuid)->lockForUpdate()->first();

        if ($existing !== null) {
            $this->assertVersion($existing, $baseVersion, $existing->status->isEditableInField());
        }

        $house = $this->resolveHouse($payload);
        $data = $this->validate($payload, CensusRules::houseVisit());

        return self::summarize($this->visits->handle($user, $campaign, $house, $data, $uuid));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function createHousehold(User $user, CensusCampaign $campaign, string $uuid, array $payload): array
    {
        $house = $this->resolveHouse($payload);
        $data = $this->validate($payload, CensusRules::household());

        return self::summarize($this->households->create($user, $campaign, $house, $data, $uuid));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function updateHousehold(User $user, CensusCampaign $campaign, string $uuid, ?int $baseVersion, array $payload): array
    {
        $household = $this->ownedHousehold($user, $campaign, $uuid);
        $this->assertVersion($household, $baseVersion, $household->status->isEditableInField());
        $data = $this->validate($payload, CensusRules::household());

        return self::summarize($this->households->update($user, $household, $data));
    }

    /**
     * @return array<string, mixed>
     */
    private function submitHousehold(User $user, CensusCampaign $campaign, string $uuid, ?int $baseVersion): array
    {
        $household = $this->ownedHousehold($user, $campaign, $uuid);
        $this->assertVersion($household, $baseVersion, in_array($household->status, [CensusRecordStatus::Draft, CensusRecordStatus::Rejected], true));

        return self::summarize($this->workflow->submit($user, $household)->fresh());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function createOccupant(User $user, CensusCampaign $campaign, string $uuid, array $payload): array
    {
        $household = $this->resolveHousehold($campaign, $payload);
        $data = $this->validateOccupant($payload, $campaign);

        return self::summarize($this->occupants->create($user, $household, $data, $uuid));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function updateOccupant(User $user, CensusCampaign $campaign, string $uuid, ?int $baseVersion, array $payload): array
    {
        $occupant = Occupant::query()->where('uuid', $uuid)->where('campaign_id', $campaign->id)->lockForUpdate()->firstOrFail();
        $this->assertOwner($user, $occupant);
        $this->assertVersion($occupant, $baseVersion, $occupant->status->isEditableInField());
        $data = $this->validateOccupant($payload, $campaign);

        return self::summarize($this->occupants->update($user, $occupant, $data));
    }

    /**
     * Occupants entered by mistake are archived, never deleted.
     *
     * @return array<string, mixed>
     */
    private function archiveOccupant(User $user, CensusCampaign $campaign, string $uuid, ?int $baseVersion): array
    {
        $occupant = Occupant::query()->where('uuid', $uuid)->where('campaign_id', $campaign->id)->lockForUpdate()->firstOrFail();
        $this->assertOwner($user, $occupant);
        $this->assertVersion($occupant, $baseVersion, $occupant->status->isEditableInField());
        $occupant->loadMissing('household');
        $this->households->assertFieldEditable($user, $occupant->household);

        $from = $occupant->status;
        $occupant->forceFill(['status' => CensusRecordStatus::Archived])->save();
        $occupant->household->reviews()->create([
            'campaign_id' => $campaign->id,
            'action' => 'archive-occupant',
            'from_status' => $from->value,
            'to_status' => CensusRecordStatus::Archived->value,
            'note' => 'Removed in the field: '.$occupant->reference_code,
            'actor_id' => $user->id,
        ]);

        return self::summarize($occupant);
    }

    private function ownedHousehold(User $user, CensusCampaign $campaign, string $uuid): Household
    {
        $household = Household::query()->where('uuid', $uuid)->where('campaign_id', $campaign->id)->lockForUpdate()->firstOrFail();
        $this->assertOwner($user, $household);

        return $household;
    }

    private function assertOwner(User $user, Model $record): void
    {
        if ((int) $record->getAttribute('enumerator_id') !== $user->id) {
            throw CensusActionException::unauthorized('Only the enumerator who registered this record can change it in the field.');
        }
    }

    /**
     * Never silently overwrite: a stale or post-review edit becomes a conflict.
     */
    private function assertVersion(Model $record, ?int $baseVersion, bool $editable): void
    {
        if (! $editable) {
            throw new SyncConflictException(ConflictClass::EditAfterReview, $record);
        }

        if ($baseVersion === null) {
            throw ValidationException::withMessages(['base_version' => 'Edits must state the version they were based on.']);
        }

        if ($baseVersion !== (int) $record->getAttribute('version')) {
            throw new SyncConflictException(ConflictClass::EditEdit, $record);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveHouse(array $payload): House
    {
        if (! empty($payload['house_uuid'])) {
            return House::query()->where('uuid', $payload['house_uuid'])->first()
                ?? throw new ParentPendingException('The house for this record has not been received yet.');
        }

        if (! empty($payload['house_id'])) {
            return House::query()->find($payload['house_id'])
                ?? throw ValidationException::withMessages(['house_id' => 'Unknown house.']);
        }

        throw ValidationException::withMessages(['house_uuid' => 'A house reference is required.']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveHousehold(CensusCampaign $campaign, array $payload): Household
    {
        $household = null;

        if (! empty($payload['household_uuid'])) {
            $household = Household::query()->where('uuid', $payload['household_uuid'])->first();

            if ($household === null) {
                throw new ParentPendingException('The household for this person has not been received yet.');
            }
        } elseif (! empty($payload['household_id'])) {
            $household = Household::query()->find($payload['household_id']);
        }

        if ($household === null || $household->campaign_id !== $campaign->id) {
            throw ValidationException::withMessages(['household_uuid' => 'The household does not belong to this campaign.']);
        }

        return $household;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function validate(array $payload, array $rules): array
    {
        return Validator::make($payload, $rules)->validate();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateOccupant(array $payload, CensusCampaign $campaign): array
    {
        $validator = Validator::make($payload, CensusRules::occupant());
        $validator->after(fn ($validator) => CensusRules::checkOccupant($validator, $payload, $campaign->reference_date));

        return $validator->validate();
    }
}
