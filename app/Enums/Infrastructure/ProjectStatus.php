<?php

namespace App\Enums\Infrastructure;

use App\Enums\Concerns\HasOptions;

enum ProjectStatus: string
{
    use HasOptions;

    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Suspended => 'Suspended',
            self::Cancelled => 'Cancelled',
        };
    }
}
