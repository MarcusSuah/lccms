<?php

namespace App\Enums\Leadership;

use App\Enums\Concerns\HasOptions;

enum SelectionMethod: string
{
    use HasOptions;

    case Elected = 'elected';
    case Appointed = 'appointed';
    case Hereditary = 'hereditary';
    case Consensus = 'consensus';
    case Acting = 'acting';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Elected => 'Elected',
            self::Appointed => 'Appointed',
            self::Hereditary => 'Hereditary / traditional',
            self::Consensus => 'Community consensus',
            self::Acting => 'Acting',
            self::Other => 'Other',
        };
    }
}
