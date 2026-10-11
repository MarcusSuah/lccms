<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RecordStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
