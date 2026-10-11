<?php

namespace App\Models\Concerns;

/**
 * Optimistic concurrency: every persisted change increments `version`.
 * Offline edits carry the version they were based on; a mismatch is a conflict.
 */
trait HasRecordVersion
{
    protected static function bootHasRecordVersion(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty() && ! $model->isDirty('version')) {
                $model->version = (int) $model->getOriginal('version') + 1;
            }
        });
    }
}
