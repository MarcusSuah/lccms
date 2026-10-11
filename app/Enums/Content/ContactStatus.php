<?php

namespace App\Enums\Content;

use App\Enums\Concerns\HasOptions;

enum ContactStatus: string
{
    use HasOptions;

    case New = 'new';
    case Read = 'read';
    case Replied = 'replied';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Read => 'Read',
            self::Replied => 'Replied',
            self::Archived => 'Archived',
        };
    }
}
