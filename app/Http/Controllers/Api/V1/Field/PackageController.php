<?php

namespace App\Http\Controllers\Api\V1\Field;

use App\Enums\Census\AssignmentRole;
use App\Http\Controllers\Controller;
use App\Models\Census\CensusCampaign;
use App\Services\Census\CensusAccess;
use App\Services\Sync\AssignmentPackageBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PackageController extends Controller
{
    public function show(Request $request, CensusCampaign $campaign, CensusAccess $access, AssignmentPackageBuilder $builder): JsonResponse
    {
        $request->validate(['since' => ['nullable', 'date']]);

        $assigned = $access->currentAssignments($request->user())
            ->contains(fn ($assignment): bool => $assignment->campaign_id === $campaign->id && $assignment->role === AssignmentRole::Enumerator);

        abort_unless($assigned, 404);

        $since = $request->filled('since') ? Carbon::parse($request->string('since')->toString()) : null;

        return response()->json($builder->build($request->user(), $campaign, $since));
    }
}
