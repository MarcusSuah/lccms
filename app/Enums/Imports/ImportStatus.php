<?php

namespace App\Enums\Imports;

use App\Enums\Concerns\HasOptions;

enum ImportStatus: string
{
    use HasOptions;

    case Uploaded = 'uploaded';
    case Validated = 'validated';
    case Invalid = 'invalid';
    case Committed = 'committed';
    case Discarded = 'discarded';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
