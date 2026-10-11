<?php

namespace App\Actions\Census;

use App\Enums\Access\Permission;
use App\Enums\Census\AssignmentRole;
use App\Enums\Census\AssignmentStatus;
use App\Enums\Geography\GeoLevel;
use App\Models\Census\CampaignAssignment;
use App\Models\Census\CensusCampaign;
use App\Models\User;
use App\Services\Census\CensusAccess;
use App\Services\Geography\GeoAreaResolver;
use App\Services\Geography\GeoScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Assigns enumerators and supervisors to areas of a campaign, and reassigns
 * work while preserving the history of who was responsible when.
 */
class SaveAssignment
{
    public function __construct(
        private GeoAreaResolver $resolver,
        private GeoScope $geoScope,
        private CensusAccess $access,
    ) {}

    /**
     * @param  array{user_id: int, role: string, supervisor_id?: int|null, level: string, area_id: int, starts_on: string, ends_on: string, expected_houses?: int|null, notes?: string|null}  $data
     */
    public function create(User $actor, CensusCampaign $campaign, array $data, ?CampaignAssignment $replaces = null): CampaignAssignment
    {
        $campaign->loadMissing('scopes');

        if ($campaign->status->isFinal()) {
            throw ValidationException::withMessages(['user_id' => 'Closed campaigns cannot receive new assignments.']);
        }

        $assignee = User::query()->findOrFail($data['user_id']);
        $role = AssignmentRole::from($data['role']);
        $requiredPermission = $role === AssignmentRole::Enumerator ? Permission::CensusEnumerate : Permission::CensusVerify;

        if (! $assignee->is_active || ! $assignee->can($requiredPermission->value)) {
            throw ValidationException::withMessages(['user_id' => "This account does not hold a role that allows {$role->label()} work."]);
        }

        $area = $this->resolver->find(GeoLevel::from($data['level']), (int) $data['area_id']);

        if ($area === null) {
            throw ValidationException::withMessages(['area_id' => 'The selected area does not exist.']);
        }

        $path = $this->resolver->fullPath($area);

        if (! $campaign->covers($path)) {
            throw ValidationException::withMessages(['area_id' => 'The area is outside this campaign’s coverage.']);
        }

        if (! $this->geoScope->allowsPath($actor, $path)) {
            throw ValidationException::withMessages(['area_id' => 'You can only assign work inside your own areas.']);
        }

        if (isset($data['supervisor_id'])) {
            $supervisor = User::query()->find($data['supervisor_id']);

            if ($supervisor === null || ! $supervisor->can(Permission::CensusVerify->value)) {
                throw ValidationException::withMessages(['supervisor_id' => 'The supervisor must hold a role that can verify records.']);
            }
        }

        $assignment = DB::transaction(function () use ($campaign, $data, $role, $path, $replaces): CampaignAssignment {
            if ($replaces !== null) {
                $replaces->forceFill(['status' => AssignmentStatus::Revoked])->save();
            }

            $assignment = new CampaignAssignment([
                'campaign_id' => $campaign->id,
                'user_id' => $data['user_id'],
                'role' => $role,
                'supervisor_id' => $data['supervisor_id'] ?? null,
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
                'expected_houses' => $data['expected_houses'] ?? null,
                'notes' => $data['notes'] ?? null,
                'reassigned_from_id' => $replaces?->id,
                ...$path,
            ]);
            $assignment->save();

            return $assignment;
        });

        $this->access->forget($assignee);

        return $assignment;
    }

    public function revoke(CampaignAssignment $assignment): CampaignAssignment
    {
        $assignment->forceFill(['status' => AssignmentStatus::Revoked])->save();

        return $assignment;
    }
}
