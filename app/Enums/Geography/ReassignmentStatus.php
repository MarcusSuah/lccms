<?php

namespace App\Enums\Geography;

use App\Enums\Concerns\HasOptions;

enum ReassignmentStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Applied = 'applied';
    case Rejected = 'rejected';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
