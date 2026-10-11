<?php

namespace App\Enums\Sync;

use App\Enums\Concerns\HasOptions;

/**
 * Outcome of one synchronised operation.
 */
enum SyncOperationStatus: string
{
    use HasOptions;

    case Applied = 'applied';
    case Duplicate = 'duplicate';
    case Rejected = 'rejected';
    case Conflict = 'conflict';
    case Retryable = 'retryable';

    public function label(): string
    {
        return match ($this) {
            self::Applied => 'Applied',
            self::Duplicate => 'Already received',
            self::Rejected => 'Rejected',
            self::Conflict => 'Conflict',
            self::Retryable => 'Retry later',
        };
    }
}
