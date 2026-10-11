<?php

namespace App\Http\Controllers\Admin\Census;

use App\Actions\Census\SaveCampaign;
use App\Enums\Access\Permission;
use App\Enums\Census\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\Census\CensusCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CampaignStatusController extends Controller
{
    public function update(Request $request, CensusCampaign $campaign, SaveCampaign $save): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::CampaignsManage->value), 403);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(CampaignStatus::class)],
        ]);

        $save->transition($request->user(), $campaign, CampaignStatus::from($validated['status']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign is now :status.', ['status' => $campaign->status->label()])]);

        return back();
    }
}
