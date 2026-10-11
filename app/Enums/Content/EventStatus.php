<?php

namespace App\Enums\Content;

use App\Enums\Concerns\HasOptions;

enum EventStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Pending = 'pending';
    case Published = 'published';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Awaiting approval',
            self::Published => 'Published',
            self::Cancelled => 'Cancelled',
        };
    }
}
