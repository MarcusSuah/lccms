<?php

namespace App\Services\Census;

use App\Enums\Access\Permission;
use App\Enums\Census\AssignmentRole;
use App\Models\Census\CampaignAssignment;
use App\Models\Census\CensusCampaign;
use App\Models\User;
use App\Services\Geography\GeoScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may see and act on which census records.
 *
 * Access to a record comes from either a standing geographic scope (area
 * administrators) or a current campaign assignment covering the record's
 * area (enumerators and supervisors). Permissions decide which actions are
 * allowed; this service decides where.
 */
class CensusAccess
{
    /**
     * @var array<int, Collection<int, CampaignAssignment>>
     */
    private array $assignments = [];

    public function __construct(private GeoScope $geoScope) {}

    /**
     * @return Collection<int, CampaignAssignment>
     */
    public function currentAssignments(User $user): Collection
    {
        return $this->assignments[$user->id] ??= CampaignAssignment::query()
            ->current()
            ->where('user_id', $user->id)
            ->get();
    }

    public function forget(User $user): void
    {
        unset($this->assignments[$user->id]);
        $this->geoScope->forget($user);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applyToRecords(Builder $query, User $user): Builder
    {
        if ($this->geoScope->for($user)->national) {
            return $query;
        }

        $model = $query->getModel();
        $hasCampaign = method_exists($model, 'campaign');
        $assignments = $this->currentAssignments($user);

        return $query->where(function (Builder $outer) use ($user, $assignments, $hasCampaign, $model): void {
            $outer->where(fn (Builder $scoped) => $this->geoScope->apply($scoped, $user));

            foreach ($assignments as $assignment) {
                $outer->orWhere(function (Builder $assigned) use ($assignment, $hasCampaign, $model): void {
                    if ($hasCampaign) {
                        $assigned->where($model->qualifyColumn('campaign_id'), $assignment->campaign_id);
                    }

                    $assigned->where($model->qualifyColumn($assignment->level()->column()), $assignment->areaId());
                });
            }
        });
    }

    /**
     * Whether the user can reach a record at all (geographic scope or assignment).
     */
    public function canAccess(User $user, Model $record): bool
    {
        if ($this->geoScope->allows($user, $record)) {
            return true;
        }

        $path = method_exists($record, 'geoPath') ? $record->geoPath() : [];
        $campaignId = $record->getAttribute('campaign_id');

        return $this->currentAssignments($user)->contains(
            fn (CampaignAssignment $assignment): bool => ($campaignId === null || $assignment->campaign_id === (int) $campaignId)
                && $assignment->covers($path),
        );
    }

    /**
     * The current enumerator assignment that authorises field work at a path.
     *
     * @param  array<string, int|null>  $path
     */
    public function enumeratorAssignment(User $user, CensusCampaign $campaign, array $path): ?CampaignAssignment
    {
        if (! $user->can(Permission::CensusEnumerate->value) || ! $campaign->status->acceptsFieldData()) {
            return null;
        }

        $campaign->loadMissing('scopes');

        if (! $campaign->covers($path)) {
            return null;
        }

        return $this->currentAssignments($user)->first(
            fn (CampaignAssignment $assignment): bool => $assignment->campaign_id === $campaign->id
                && $assignment->role === AssignmentRole::Enumerator
                && $assignment->covers($path),
        );
    }

    /**
     * Verification is allowed to holders of census.verify within their
     * geographic scope, or to the supervisor assigned to the area.
     */
    public function canVerify(User $user, Model $record): bool
    {
        if (! $user->can(Permission::CensusVerify->value)) {
            return false;
        }

        if ($this->geoScope->allows($user, $record)) {
            return true;
        }

        $path = $record->geoPath();

        return $this->currentAssignments($user)->contains(
            fn (CampaignAssignment $assignment): bool => $assignment->role === AssignmentRole::Supervisor
                && $assignment->campaign_id === (int) $record->getAttribute('campaign_id')
                && $assignment->covers($path),
        );
    }

    /**
     * Final approval is reserved for census.approve holders with standing
     * geographic authority over the record.
     */
    public function canApprove(User $user, Model $record): bool
    {
        return $user->can(Permission::CensusApprove->value) && $this->geoScope->allows($user, $record);
    }

    /**
     * Names and dates of birth: reviewers with personal-data permission, or the
     * enumerator who captured the record.
     */
    public function canViewPersonalData(User $user, Model $record): bool
    {
        if ((int) $record->getAttribute('enumerator_id') === $user->id && $user->can(Permission::CensusEnumerate->value)) {
            return true;
        }

        return $user->can(Permission::CensusViewPersonalData->value) && $this->canAccess($user, $record);
    }

    /**
     * Campaign ids whose coverage overlaps the user's reach.
     *
     * @return list<int>|null null means every campaign
     */
    public function visibleCampaignIds(User $user): ?array
    {
        $scopes = $this->geoScope->for($user);

        if ($scopes->national) {
            return null;
        }

        $ids = $this->currentAssignments($user)->pluck('campaign_id')->all();

        CensusCampaign::query()->with('scopes')->get()->each(function (CensusCampaign $campaign) use ($scopes, &$ids): void {
            foreach ($campaign->scopes as $campaignScope) {
                foreach ($scopes->areas as $area) {
                    $campaignInsideUser = (int) ($campaignScope->path()[$area['level']->column()] ?? 0) === $area['id'];
                    $userInsideCampaign = $campaignScope->covers($area['path']);

                    if ($campaignInsideUser || $userInsideCampaign) {
                        $ids[] = $campaign->id;

                        return;
                    }
                }
            }
        });

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
