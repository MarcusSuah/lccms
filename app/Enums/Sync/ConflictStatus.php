<?php

namespace App\Enums\Sync;

use App\Enums\Concerns\HasOptions;

enum ConflictStatus: string
{
    use HasOptions;

    case Open = 'open';
    case KeptServer = 'kept_server';
    case AppliedClient = 'applied_client';
    case ConvertedToCorrection = 'converted_correction';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::KeptServer => 'Server version kept',
            self::AppliedClient => 'Device version applied',
            self::ConvertedToCorrection => 'Sent as correction request',
        };
    }
}
