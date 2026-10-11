<?php

namespace App\Actions\Complaints;

use App\Enums\Access\Permission;
use App\Enums\Complaints\ComplaintStatus;
use App\Models\Complaints\Complaint;
use App\Models\User;
use App\Notifications\ComplaintAssigned;
use App\Notifications\ComplaintUpdated;
use App\Services\Geography\GeoScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Complaint handling by authorised officers: status workflow with public
 * responses, assignment and internal notes.
 */
class ManageComplaint
{
    public function __construct(private GeoScope $geoScope) {}

    public function transition(User $actor, Complaint $complaint, ComplaintStatus $target, ?string $publicResponse, ?string $resolution = null): Complaint
    {
        if (! in_array($target, $complaint->status->allowedTransitions(), true)) {
            throw ValidationException::withMessages(['status' => "A complaint cannot move from {$complaint->status->label()} to {$target->label()}."]);
        }

        if ($target === ComplaintStatus::Rejected && blank($publicResponse)) {
            throw ValidationException::withMessages(['public_response' => 'Give the complainant the reason for rejecting the complaint.']);
        }

        if ($target === ComplaintStatus::Resolved && blank($resolution)) {
            throw ValidationException::withMessages(['resolution_summary' => 'Summarise how the complaint was resolved.']);
        }

        DB::transaction(function () use ($actor, $complaint, $target, $publicResponse, $resolution): void {
            $from = $complaint->status;

            $complaint->forceFill([
                'status' => $target,
                'resolution_summary' => $resolution ?? $complaint->resolution_summary,
                'resolved_at' => $target === ComplaintStatus::Resolved ? now() : $complaint->resolved_at,
                'closed_at' => $target === ComplaintStatus::Closed ? now() : $complaint->closed_at,
            ])->save();

            $complaint->history()->create([
                'from_status' => $from,
                'to_status' => $target,
                'public_response' => $publicResponse,
                'actor_id' => $actor->id,
            ]);
        });

        if ($complaint->complainant_email && filled($publicResponse)) {
            Notification::route('mail', $complaint->complainant_email)
                ->notify(new ComplaintUpdated($complaint->public_reference, $target->label(), $publicResponse));
        }

        return $complaint;
    }

    public function assign(User $actor, Complaint $complaint, User $assignee): Complaint
    {
        if (! $assignee->is_active || ! $assignee->can(Permission::ComplaintsManage->value)) {
            throw ValidationException::withMessages(['assigned_to' => 'The assignee must be an active complaint officer.']);
        }

        if (! $this->geoScope->allows($assignee, $complaint)) {
            throw ValidationException::withMessages(['assigned_to' => 'The assignee does not cover this complaint’s area.']);
        }

        DB::transaction(function () use ($actor, $complaint, $assignee): void {
            $complaint->forceFill(['assigned_to' => $assignee->id])->save();

            if (in_array(ComplaintStatus::Assigned, $complaint->status->allowedTransitions(), true)) {
                $from = $complaint->status;
                $complaint->forceFill(['status' => ComplaintStatus::Assigned])->save();
                $complaint->history()->create(['from_status' => $from, 'to_status' => ComplaintStatus::Assigned, 'actor_id' => $actor->id]);
            }
        });

        $assignee->notify(new ComplaintAssigned($complaint->public_reference, $complaint->subject));

        return $complaint;
    }

    public function note(User $actor, Complaint $complaint, string $body): void
    {
        $complaint->notes()->create(['author_id' => $actor->id, 'body' => $body]);
    }
}
