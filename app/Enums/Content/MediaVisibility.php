<?php

namespace App\Enums\Content;

use App\Enums\Concerns\HasOptions;

enum MediaVisibility: string
{
    use HasOptions;

    case Public = 'public';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public website',
            self::Internal => 'Staff only',
        };
    }
}
