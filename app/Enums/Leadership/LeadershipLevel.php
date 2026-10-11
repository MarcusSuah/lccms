<?php

namespace App\Enums\Leadership;

use App\Enums\Concerns\HasOptions;

enum LeadershipLevel: string
{
    use HasOptions;

    case Community = 'community';
    case Block = 'block';

    public function label(): string
    {
        return match ($this) {
            self::Community => 'Community',
            self::Block => 'Block',
        };
    }
}
