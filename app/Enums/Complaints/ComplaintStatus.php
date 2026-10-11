<?php

namespace App\Enums\Complaints;

use App\Enums\Concerns\HasOptions;

/**
 * Complaint workflow.
 */
enum ComplaintStatus: string
{
    use HasOptions;

    case Submitted = 'submitted';
    case Received = 'received';
    case UnderReview = 'under_review';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case AwaitingInformation = 'awaiting_information';
    case Resolved = 'resolved';
    case Rejected = 'rejected';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Received => 'Received',
            self::UnderReview => 'Under review',
            self::Assigned => 'Assigned',
            self::InProgress => 'In progress',
            self::AwaitingInformation => 'Awaiting information',
            self::Resolved => 'Resolved',
            self::Rejected => 'Rejected',
            self::Closed => 'Closed',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Submitted => [self::Received, self::Rejected],
            self::Received => [self::UnderReview, self::Assigned, self::Rejected],
            self::UnderReview => [self::Assigned, self::AwaitingInformation, self::Rejected, self::Resolved],
            self::Assigned => [self::InProgress, self::AwaitingInformation, self::Resolved, self::Rejected],
            self::InProgress => [self::AwaitingInformation, self::Resolved, self::Rejected],
            self::AwaitingInformation => [self::UnderReview, self::InProgress, self::Rejected, self::Closed],
            self::Resolved => [self::Closed, self::InProgress],
            self::Rejected => [self::Closed],
            self::Closed => [],
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Resolved, self::Rejected, self::Closed], true);
    }
}
