<?php

namespace App\Services\Sync;

use App\Enums\Sync\ConflictClass;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Raised while applying an operation when the server record has moved on.
 * The processor turns it into a preserved SyncConflict.
 */
class SyncConflictException extends RuntimeException
{
    public function __construct(
        public readonly ConflictClass $conflictClass,
        public readonly Model $record,
    ) {
        parent::__construct($conflictClass->label());
    }
}
