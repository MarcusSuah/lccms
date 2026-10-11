<?php

namespace App\Actions\Census;

use App\Enums\Census\CensusRecordStatus;
use App\Enums\Census\OccupancyStatus;
use App\Enums\Census\RelationshipToHead;
use App\Exceptions\CensusActionException;
use App\Models\Census\HouseEnumeration;
use App\Models\Census\Household;
use App\Models\Census\Occupant;
use App\Models\User;
use App\Services\Census\CensusAccess;
use App\Services\Census\CensusStatistics;
use Illuminate\Support\Facades\DB;

/**
 * Status transitions for census records:
 *
 *   draft → submitted → verified → approved
 *              ↘ rejected (returned to the enumerator) ↗
 *   any non-approved status → archived (with reason)
 *
 * A household's occupants always move with it. Approving a household also
 * approves the visit record of its house. Only approved records enter
 * official totals. Approved records change only through corrections.
 */
class CensusWorkflow
{
    public function __construct(
        private CensusAccess $access,
        private SaveHousehold $households,
    ) {}

    public function submit(User $actor, Household $household): Household
    {
        $this->households->assertFieldEditable($actor, $household);

        if (! in_array($household->status, [CensusRecordStatus::Draft, CensusRecordStatus::Rejected], true)) {
            throw new CensusActionException(CensusActionException::INVALID_TRANSITION, 'Only draft or returned households can be submitted.');
        }

        $occupants = $household->occupants()->where('status', '!=', CensusRecordStatus::Archived)->get(['id', 'relationship_to_head']);

        if ($occupants->isEmpty()) {
            throw new CensusActionException(CensusActionException::INCOMPLETE, 'Add at least one occupant before submitting the household.');
        }

        if ($occupants->where('relationship_to_head', RelationshipToHead::Head)->count() !== 1) {
            throw new CensusActionException(CensusActionException::INCOMPLETE, 'Exactly one occupant must be recorded as head of household.');
        }

        return $this->transition($actor, $household, CensusRecordStatus::Submitted, 'submit', null, [
            'submitted_at' => now(),
        ]);
    }

    public function verify(User $actor, Household $household, ?string $note = null): Household
    {
        $this->assertReviewable($household);

        if (! $this->access->canVerify($actor, $household)) {
            throw CensusActionException::unauthorized('You cannot verify records in this area.');
        }

        $this->assertStatus($household, [CensusRecordStatus::Submitted], 'Only submitted households can be verified.');

        return $this->transition($actor, $household, CensusRecordStatus::Verified, 'verify', $note, [
            'verified_at' => now(),
            'verified_by' => $actor->id,
        ]);
    }

    public function approve(User $actor, Household $household, ?string $note = null): Household
    {
        $this->assertReviewable($household);

        if (! $this->access->canApprove($actor, $household)) {
            throw CensusActionException::unauthorized('You cannot approve records in this area.');
        }

        if ($household->enumerator_id === $actor->id) {
            throw CensusActionException::unauthorized('A record cannot be approved by the person who collected it.');
        }

        $this->assertStatus($household, [CensusRecordStatus::Verified], 'Only verified households can be approved.');

        return $this->transition($actor, $household, CensusRecordStatus::Approved, 'approve', $note, [
            'approved_at' => now(),
            'approved_by' => $actor->id,
        ]);
    }

    public function reject(User $actor, Household $household, string $note): Household
    {
        $this->assertReviewable($household);

        if (! $this->access->canVerify($actor, $household)) {
            throw CensusActionException::unauthorized('You cannot review records in this area.');
        }

        $this->assertStatus($household, [CensusRecordStatus::Submitted, CensusRecordStatus::Verified], 'Only submitted or verified households can be returned.');

        return $this->transition($actor, $household, CensusRecordStatus::Rejected, 'reject', $note, [
            'verified_at' => null,
            'verified_by' => null,
        ]);
    }

    public function archive(User $actor, Household $household, string $note): Household
    {
        if (! $this->access->canVerify($actor, $household)) {
            throw CensusActionException::unauthorized('You cannot archive records in this area.');
        }

        if ($household->status === CensusRecordStatus::Approved) {
            throw new CensusActionException(CensusActionException::NOT_EDITABLE, 'Approved households are changed only through a correction request.');
        }

        $this->assertStatus($household, [CensusRecordStatus::Draft, CensusRecordStatus::Submitted, CensusRecordStatus::Verified, CensusRecordStatus::Rejected], 'This household is already archived.');

        return $this->transition($actor, $household, CensusRecordStatus::Archived, 'archive', $note);
    }

    /**
     * Review of a house visit that has no households (vacant, refused …).
     */
    public function reviewVisit(User $actor, HouseEnumeration $visit, string $action, ?string $note = null): HouseEnumeration
    {
        $visit->loadMissing('campaign');

        if ($visit->occupancy_status === OccupancyStatus::Occupied) {
            throw new CensusActionException(CensusActionException::INVALID_TRANSITION, 'Occupied houses are reviewed through their households.');
        }

        [$allowed, $target, $permitted] = match ($action) {
            'submit' => [[CensusRecordStatus::Draft, CensusRecordStatus::Rejected], CensusRecordStatus::Submitted, $visit->enumerator_id === $actor->id && $this->access->canAccess($actor, $visit)],
            'verify' => [[CensusRecordStatus::Submitted], CensusRecordStatus::Verified, $this->access->canVerify($actor, $visit)],
            'approve' => [[CensusRecordStatus::Verified], CensusRecordStatus::Approved, $this->access->canApprove($actor, $visit) && $visit->enumerator_id !== $actor->id],
            'reject' => [[CensusRecordStatus::Submitted, CensusRecordStatus::Verified], CensusRecordStatus::Rejected, $this->access->canVerify($actor, $visit)],
            default => throw new CensusActionException(CensusActionException::INVALID_TRANSITION, 'Unknown action.'),
        };

        if (! $permitted) {
            throw CensusActionException::unauthorized();
        }

        if ($action === 'submit' ? ! $visit->campaign->status->acceptsFieldData() : ! $visit->campaign->status->acceptsReview()) {
            throw new CensusActionException(CensusActionException::CAMPAIGN_NOT_ACCEPTING, 'The campaign is not accepting this action.');
        }

        if (! in_array($visit->status, $allowed, true)) {
            throw new CensusActionException(CensusActionException::INVALID_TRANSITION, 'This visit cannot be '.$action.'d in its current status.');
        }

        return DB::transaction(function () use ($actor, $visit, $target, $action, $note): HouseEnumeration {
            $from = $visit->status;
            $visit->forceFill(['status' => $target])->save();
            $this->log($visit, $visit->campaign_id, $action, $from, $target, $note, $actor);
            CensusStatistics::invalidate($visit->campaign_id);

            return $visit;
        });
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function transition(User $actor, Household $household, CensusRecordStatus $target, string $action, ?string $note, array $extra = []): Household
    {
        return DB::transaction(function () use ($actor, $household, $target, $action, $note, $extra): Household {
            $household = Household::query()->lockForUpdate()->findOrFail($household->id);
            $from = $household->status;

            $household->forceFill([...$extra, 'status' => $target, 'review_note' => $note ?? $household->review_note])->save();

            Occupant::query()
                ->where('household_id', $household->id)
                ->where('status', '!=', CensusRecordStatus::Archived)
                ->update(['status' => $target, 'version' => DB::raw('version + 1'), 'updated_at' => now()]);

            if (in_array($target, [CensusRecordStatus::Submitted, CensusRecordStatus::Verified, CensusRecordStatus::Approved], true)) {
                $visit = HouseEnumeration::query()
                    ->where('house_id', $household->house_id)
                    ->where('campaign_id', $household->campaign_id)
                    ->first();

                if ($visit !== null && $this->rank($visit->status) < $this->rank($target)) {
                    $visit->forceFill(['status' => $target])->save();
                }
            }

            $this->log($household, $household->campaign_id, $action, $from, $target, $note, $actor);
            CensusStatistics::invalidate($household->campaign_id);

            return $household;
        });
    }

    private function assertReviewable(Household $household): void
    {
        $household->loadMissing('campaign');

        if (! $household->campaign->status->acceptsReview()) {
            throw new CensusActionException(CensusActionException::CAMPAIGN_NOT_ACCEPTING, 'The campaign is not open for review.');
        }
    }

    /**
     * @param  list<CensusRecordStatus>  $allowed
     */
    private function assertStatus(Household $household, array $allowed, string $message): void
    {
        if (! in_array($household->status, $allowed, true)) {
            throw new CensusActionException(CensusActionException::INVALID_TRANSITION, $message);
        }
    }

    private function rank(CensusRecordStatus $status): int
    {
        return match ($status) {
            CensusRecordStatus::Draft, CensusRecordStatus::Rejected => 0,
            CensusRecordStatus::Submitted => 1,
            CensusRecordStatus::Verified => 2,
            CensusRecordStatus::Approved => 3,
            CensusRecordStatus::Archived => -1,
        };
    }

    private function log(Household|HouseEnumeration $record, int $campaignId, string $action, CensusRecordStatus $from, CensusRecordStatus $to, ?string $note, User $actor): void
    {
        $record->reviews()->create([
            'campaign_id' => $campaignId,
            'action' => $action,
            'from_status' => $from->value,
            'to_status' => $to->value,
            'note' => $note,
            'actor_id' => $actor->id,
        ]);
    }
}
