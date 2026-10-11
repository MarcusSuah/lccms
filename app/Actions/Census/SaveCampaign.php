<?php

namespace App\Actions\Census;

use App\Enums\Census\AssignmentStatus;
use App\Enums\Census\CampaignStatus;
use App\Enums\Geography\GeoLevel;
use App\Models\Census\CensusCampaign;
use App\Models\User;
use App\Services\Geography\GeoAreaResolver;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates and edits campaigns and moves them through their lifecycle.
 * A campaign's coverage is fixed once field work starts.
 */
class SaveCampaign
{
    public function __construct(private GeoAreaResolver $resolver) {}

    /**
     * @param  array{reference_code: string, name: string, description?: string|null, instructions?: string|null, start_date: string, end_date: string, reference_date: string, scopes?: list<array{level: string, area_id: int}>}  $data
     */
    public function handle(array $data, ?CensusCampaign $campaign = null): CensusCampaign
    {
        return DB::transaction(function () use ($data, $campaign): CensusCampaign {
            $campaign ??= new CensusCampaign;
            $coverageLocked = $campaign->exists && ! in_array($campaign->status, [CampaignStatus::Draft, CampaignStatus::Planned], true);

            if ($campaign->exists && $campaign->status->isFinal()) {
                throw ValidationException::withMessages(['name' => 'Closed campaigns cannot be edited.']);
            }

            $campaign->fill(Arr::except($data, ['scopes']))->save();

            if (! $coverageLocked && array_key_exists('scopes', $data)) {
                $campaign->scopes()->delete();

                foreach ($data['scopes'] as $scope) {
                    $area = $this->resolver->find(GeoLevel::from($scope['level']), (int) $scope['area_id']);
                    $campaign->scopes()->create($this->resolver->fullPath($area));
                }
            }

            return $campaign;
        });
    }

    public function transition(User $actor, CensusCampaign $campaign, CampaignStatus $target): CensusCampaign
    {
        if (! in_array($target, $campaign->status->allowedTransitions(), true)) {
            throw ValidationException::withMessages([
                'status' => "A campaign cannot move from {$campaign->status->label()} to {$target->label()}.",
            ]);
        }

        if ($target === CampaignStatus::Active && $campaign->scopes()->doesntExist()) {
            throw ValidationException::withMessages(['status' => 'Define the geographic coverage before starting field work.']);
        }

        return DB::transaction(function () use ($actor, $campaign, $target): CensusCampaign {
            $campaign->forceFill(['status' => $target]);

            if ($target === CampaignStatus::Closed) {
                $campaign->forceFill(['closed_at' => now(), 'closed_by' => $actor->id]);
                $campaign->assignments()
                    ->where('status', AssignmentStatus::Active)
                    ->update(['status' => AssignmentStatus::Completed->value, 'updated_at' => now()]);
            }

            $campaign->save();

            activity('census_campaigns')
                ->performedOn($campaign)
                ->causedBy($actor)
                ->event('status-changed')
                ->withProperties(['status' => $target->value])
                ->log('status changed to '.$target->value);

            return $campaign;
        });
    }
}
