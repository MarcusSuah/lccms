<?php

namespace App\Services\Geography;

use App\Enums\Geography\GeoLevel;

/**
 * The resolved geographic reach of one user.
 */
final readonly class ScopeSet
{
    /**
     * @param  list<array{level: GeoLevel, id: int, path: array<string, int|null>}>  $areas
     */
    public function __construct(
        public bool $national,
        public array $areas,
    ) {}

    public function isEmpty(): bool
    {
        return ! $this->national && $this->areas === [];
    }
}
