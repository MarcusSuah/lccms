<?php

namespace App\Enums\Sync;

use App\Enums\Concerns\HasOptions;

/**
 * Why an offline change could not be applied directly.
 */
enum ConflictClass: string
{
    use HasOptions;

    case EditEdit = 'edit_edit';
    case EditAfterReview = 'edit_after_review';
    case ScopeChanged = 'scope_changed';

    public function label(): string
    {
        return match ($this) {
            self::EditEdit => 'Changed on the server since the device copy',
            self::EditAfterReview => 'Record was reviewed before the edit arrived',
            self::ScopeChanged => 'Assignment ended before the edit arrived',
        };
    }
}
