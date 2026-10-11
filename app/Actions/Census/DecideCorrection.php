<?php

namespace App\Actions\Census;

use App\Enums\Access\Permission;
use App\Enums\Census\CorrectionStatus;
use App\Exceptions\CensusActionException;
use App\Models\Census\Household;
use App\Models\Census\Occupant;
use App\Models\Census\RecordCorrection;
use App\Models\User;
use App\Services\Census\CensusStatistics;
use App\Services\Geography\GeoScope;
use Illuminate\Support\Facades\DB;

/**
 * Approves (and applies) or rejects a pending correction. Four-eyes rule:
 * the requester can never decide their own correction.
 */
class DecideCorrection
{
    public function __construct(private GeoScope $geoScope) {}

    public function approve(User $actor, RecordCorrection $correction, ?string $note): RecordCorrection
    {
        return DB::transaction(function () use ($actor, $correction, $note): RecordCorrection {
            $correction = RecordCorrection::query()->lockForUpdate()->findOrFail($correction->id);
            $record = $this->authorize($actor, $correction);

            foreach ($correction->changes as $field => $change) {
                $record->setAttribute($field, $change['to']);
            }

            $record->save();

            $correction->forceFill([
                'status' => CorrectionStatus::Approved,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_note' => $note,
                'applied_at' => now(),
            ])->save();

            $household = $record instanceof Household ? $record : $record->household;
            $household->reviews()->create([
                'campaign_id' => $correction->campaign_id,
                'action' => 'correct',
                'from_status' => $household->status->value,
                'to_status' => $household->status->value,
                'note' => 'Correction #'.$correction->id.' applied to '.implode(', ', array_keys($correction->changes)),
                'actor_id' => $actor->id,
            ]);

            CensusStatistics::invalidate($correction->campaign_id);

            return $correction;
        });
    }

    public function reject(User $actor, RecordCorrection $correction, string $note): RecordCorrection
    {
        $this->authorize($actor, $correction);

        $correction->forceFill([
            'status' => CorrectionStatus::Rejected,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ])->save();

        return $correction;
    }

    private function authorize(User $actor, RecordCorrection $correction): Household|Occupant
    {
        if ($correction->status !== CorrectionStatus::Pending) {
            throw new CensusActionException(CensusActionException::INVALID_TRANSITION, 'This correction has already been decided.');
        }

        if ($correction->requested_by === $actor->id) {
            throw CensusActionException::unauthorized('A correction must be decided by someone other than the requester.');
        }

        /** @var Household|Occupant $record */
        $record = $correction->correctable;

        if (! $actor->can(Permission::CensusApproveCorrection->value) || ! $this->geoScope->allows($actor, $record)) {
            throw CensusActionException::unauthorized('You cannot decide corrections in this area.');
        }

        $record->loadMissing('campaign');

        if ($record->campaign->status->isFinal()) {
            throw new CensusActionException(CensusActionException::CAMPAIGN_NOT_ACCEPTING, 'The campaign is closed. Its approved results are final.');
        }

        return $record;
    }
}
