<?php

namespace App\Enums\Census;

use App\Enums\Concerns\HasOptions;

/**
 * Workflow status shared by house enumerations, households and occupants.
 */
enum CensusRecordStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Verified = 'verified';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Verified => 'Verified',
            self::Approved => 'Approved',
            self::Rejected => 'Returned for correction',
            self::Archived => 'Archived',
        };
    }

    /**
     * Statuses in which the enumerator may still change the record directly.
     */
    public function isEditableInField(): bool
    {
        return in_array($this, [self::Draft, self::Rejected, self::Submitted], true);
    }

    /**
     * Statuses counted as at least submitted.
     *
     * @return list<self>
     */
    public static function submittedOrLater(): array
    {
        return [self::Submitted, self::Verified, self::Approved];
    }
}
