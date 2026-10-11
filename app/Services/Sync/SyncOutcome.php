<?php

namespace App\Services\Sync;

use App\Enums\Sync\SyncOperationStatus;

/**
 * The result of applying one operation, before it is persisted.
 */
final readonly class SyncOutcome
{
    /**
     * @param  array<string, mixed>|null  $record
     * @param  array<string, list<string>>|null  $errors
     */
    public function __construct(
        public SyncOperationStatus $status,
        public ?array $record = null,
        public ?string $code = null,
        public ?array $errors = null,
        public ?int $conflictId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $record
     */
    public static function applied(array $record): self
    {
        return new self(SyncOperationStatus::Applied, $record);
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function rejected(string $code, array $errors): self
    {
        return new self(SyncOperationStatus::Rejected, code: $code, errors: $errors);
    }

    public static function retryable(string $code, string $message): self
    {
        return new self(SyncOperationStatus::Retryable, code: $code, errors: ['operation' => [$message]]);
    }
}
