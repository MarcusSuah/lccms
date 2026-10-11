<?php

namespace App\Http\Controllers\Admin\Census;

use App\Enums\Access\Permission;
use App\Enums\Census\CensusRecordStatus;
use App\Enums\Census\RelationshipToHead;
use App\Enums\Census\SchoolAttendance;
use App\Enums\Census\Sex;
use App\Http\Controllers\Controller;
use App\Models\Census\CensusCampaign;
use App\Models\Census\DuplicateFlag;
use App\Models\Census\Household;
use App\Models\Census\Occupant;
use App\Models\Census\RecordCorrection;
use App\Models\Census\RecordReview;
use App\Services\Census\CensusAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Supervisor and administrator view of enumerated households, used for the
 * review queue. Personal data is shown only to authorised reviewers.
 */
class HouseholdController extends Controller
{
    public function index(Request $request, CensusAccess $access): Response
    {
        $user = $request->user();
        abort_unless($user->can(Permission::CensusView->value), 403);

        $visible = $access->visibleCampaignIds($user);
        $campaigns = CensusCampaign::query()
            ->when($visible !== null, fn ($query) => $query->whereIn('id', $visible))
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'reference_code', 'status']);

        $filters = $request->validate([
            'campaign' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(CensusRecordStatus::class)],
            'search' => ['nullable', 'string', 'max:80'],
        ]);

        $campaign = $campaigns->firstWhere('id', (int) ($filters['campaign'] ?? 0)) ?? $campaigns->first();
        $canSeeNames = $user->can(Permission::CensusViewPersonalData->value);

        $households = $campaign === null ? null : Household::query()
            ->where('campaign_id', $campaign->id)
            ->accessibleBy($user)
            ->with(['enumerator:id,name', 'house:id,reference_code,local_house_number', 'block:id,name', 'community:id,name'])
            ->withCount(['occupants' => fn (Builder $query) => $query->where('status', '!=', CensusRecordStatus::Archived)])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('reference_code', 'like', '%'.$search.'%'))
            ->orderByRaw("FIELD(status, 'submitted', 'verified', 'rejected', 'draft', 'approved', 'archived')")
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Household $household): array => [
                'id' => $household->id,
                'reference_code' => $household->reference_code,
                'status' => $household->status->value,
                'occupants_count' => $household->occupants_count,
                'house' => $household->house?->reference_code,
                'area' => trim(($household->community?->name ?? '').' › '.($household->block?->name ?? ''), ' ›'),
                'enumerator' => $household->enumerator?->name,
                'contact_name' => $canSeeNames ? $household->contact_name : null,
                'updated_at' => $household->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/census/households/index', [
            'campaigns' => $campaigns->map->only(['id', 'name', 'reference_code', 'status'])->values(),
            'campaign' => $campaign?->only(['id', 'name', 'reference_code', 'status']),
            'households' => $households,
            'filters' => [
                'campaign' => $campaign?->id,
                'status' => $filters['status'] ?? '',
                'search' => $filters['search'] ?? '',
            ],
            'statusOptions' => CensusRecordStatus::options(),
        ]);
    }

    public function show(Request $request, Household $household, CensusAccess $access): Response
    {
        $user = $request->user();
        abort_unless($user->can(Permission::CensusView->value) && $access->canAccess($user, $household), 404);

        $household->load([
            'campaign', 'house', 'enumerator:id,name', 'supervisor:id,name',
            'county:id,name', 'district:id,name', 'clan:id,name', 'town:id,name', 'community:id,name', 'block:id,name',
        ]);
        $canSeeNames = $access->canViewPersonalData($user, $household);

        $occupants = $household->occupants()
            ->orderByRaw("FIELD(relationship_to_head, 'head') DESC")
            ->orderBy('id')
            ->get();

        $flags = DuplicateFlag::query()
            ->whereIn('occupant_id', $occupants->pluck('id'))
            ->with('matchedOccupant:id,reference_code,household_id')
            ->latest('id')
            ->get();

        return Inertia::render('admin/census/households/show', [
            'household' => [
                'id' => $household->id,
                'reference_code' => $household->reference_code,
                'status' => $household->status->value,
                'version' => $household->version,
                'residence_type' => $household->residence_type->label(),
                'contact_name' => $canSeeNames ? $household->contact_name : null,
                'contact_phone' => $canSeeNames ? $household->contact_phone : null,
                'review_note' => $household->review_note,
                'sequence_in_house' => $household->sequence_in_house,
                'enumerator' => $household->enumerator?->name,
                'supervisor' => $household->supervisor?->name,
                'submitted_at' => $household->submitted_at?->toIso8601String(),
                'verified_at' => $household->verified_at?->toIso8601String(),
                'approved_at' => $household->approved_at?->toIso8601String(),
                'location' => collect([$household->county, $household->district, $household->clan, $household->town, $household->community, $household->block])
                    ->filter()->pluck('name')->all(),
                'house' => [
                    'reference_code' => $household->house->reference_code,
                    'local_house_number' => $household->house->local_house_number,
                    'building_type' => $household->house->building_type?->label(),
                    'has_coordinates' => $household->house->latitude !== null,
                ],
                'campaign' => $household->campaign->only(['id', 'name', 'reference_code']) + [
                    'status' => $household->campaign->status->value,
                    'reference_date' => $household->campaign->reference_date->toDateString(),
                ],
            ],
            'occupants' => $occupants->map(fn (Occupant $occupant): array => [
                'id' => $occupant->id,
                'reference_code' => $occupant->reference_code,
                'full_name' => $canSeeNames ? $occupant->full_name : null,
                'date_of_birth' => $canSeeNames ? $occupant->date_of_birth?->toDateString() : null,
                'age' => $occupant->ageOn($household->campaign->reference_date),
                'age_is_estimated' => $occupant->age_is_estimated,
                'sex' => $occupant->sex->value,
                'sex_label' => $occupant->sex->label(),
                'relationship_to_head' => $occupant->relationship_to_head->value,
                'relationship_label' => $occupant->relationship_to_head->label(),
                'usual_resident' => $occupant->usual_resident,
                'school_attendance' => $occupant->school_attendance?->value,
                'school_label' => $occupant->school_attendance?->label(),
                'status' => $occupant->status->value,
            ]),
            'reviews' => RecordReview::query()
                ->where('reviewable_type', $household->getMorphClass())
                ->where('reviewable_id', $household->id)
                ->with('actor:id,name')
                ->latest('id')
                ->get()
                ->map(fn (RecordReview $review): array => [
                    'id' => $review->id,
                    'action' => $review->action,
                    'from' => $review->from_status,
                    'to' => $review->to_status,
                    'note' => $review->note,
                    'actor' => $review->actor?->name,
                    'at' => $review->created_at->toIso8601String(),
                ]),
            'corrections' => RecordCorrection::query()
                ->where(fn (Builder $query) => $query
                    ->where(fn (Builder $inner) => $inner->where('correctable_type', $household->getMorphClass())->where('correctable_id', $household->id))
                    ->orWhere(fn (Builder $inner) => $inner->where('correctable_type', (new Occupant)->getMorphClass())->whereIn('correctable_id', $occupants->pluck('id'))))
                ->with(['requester:id,name', 'decider:id,name'])
                ->latest('id')
                ->get()
                ->map(fn (RecordCorrection $correction): array => [
                    'id' => $correction->id,
                    'target' => $correction->correctable_type === $household->getMorphClass() ? 'Household' : 'Occupant #'.$correction->correctable_id,
                    'fields' => array_keys($correction->changes),
                    'changes' => $canSeeNames ? $correction->changes : null,
                    'reason' => $correction->reason,
                    'status' => $correction->status->value,
                    'requested_by' => $correction->requester?->name,
                    'decided_by' => $correction->decider?->name,
                    'decision_note' => $correction->decision_note,
                ]),
            'duplicates' => $flags->map(fn (DuplicateFlag $flag): array => [
                'id' => $flag->id,
                'occupant_id' => $flag->occupant_id,
                'matched_reference' => $flag->matchedOccupant?->reference_code,
                'matched_household_id' => $flag->matchedOccupant?->household_id,
                'score' => $flag->score,
                'reasons' => $flag->reasons,
                'status' => $flag->status->value,
            ]),
            'options' => [
                'sex' => Sex::options(),
                'relationship' => RelationshipToHead::options(),
                'school' => SchoolAttendance::options(),
            ],
            'can' => [
                'viewPersonalData' => $canSeeNames,
                'verify' => $access->canVerify($user, $household),
                'approve' => $access->canApprove($user, $household) && $household->enumerator_id !== $user->id,
                'requestCorrection' => $user->can(Permission::CensusCorrect->value),
                'decideCorrection' => $user->can(Permission::CensusApproveCorrection->value),
                'reviewDuplicates' => $user->can(Permission::CensusReviewDuplicates->value),
            ],
        ]);
    }
}
