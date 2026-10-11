<?php

namespace App\Enums\Census;

use App\Enums\Concerns\HasOptions;

enum RelationshipToHead: string
{
    use HasOptions;

    case Head = 'head';
    case Spouse = 'spouse';
    case Child = 'child';
    case StepChild = 'step_child';
    case Grandchild = 'grandchild';
    case Parent = 'parent';
    case Sibling = 'sibling';
    case OtherRelative = 'other_relative';
    case NonRelative = 'non_relative';

    public function label(): string
    {
        return match ($this) {
            self::Head => 'Head of household',
            self::Spouse => 'Spouse / partner',
            self::Child => 'Son / daughter',
            self::StepChild => 'Step-child',
            self::Grandchild => 'Grandchild',
            self::Parent => 'Parent',
            self::Sibling => 'Brother / sister',
            self::OtherRelative => 'Other relative',
            self::NonRelative => 'Non-relative',
        };
    }
}
