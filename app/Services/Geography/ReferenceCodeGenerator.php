<?php

namespace App\Services\Geography;

use App\Enums\Geography\GeoLevel;
use App\Models\Geography\GeoArea;

/**
 * Generates human-readable, hierarchical reference codes such as
 * LR-MO-D02-CL003. Must be called inside a transaction; the parent row is
 * locked so concurrent creations under the same parent cannot collide.
 */
class ReferenceCodeGenerator
{
    public function nextChildCode(GeoArea $parent): string
    {
        $childLevel = $parent::level()->child();

        abort_if($childLevel === null, 422, 'Blocks cannot have geographic children.');

        $parent->newQuery()->whereKey($parent->getKey())->lockForUpdate()->first();

        $class = $childLevel->modelClass();
        $prefix = $parent->reference_code.'-'.$childLevel->codePrefix();
        $width = $childLevel === GeoLevel::District ? 2 : 3;

        $sequence = $class::query()
            ->where($parent::level()->column(), $parent->getKey())
            ->count() + 1;

        do {
            $code = $prefix.str_pad((string) $sequence, $width, '0', STR_PAD_LEFT);
            $sequence++;
        } while ($class::query()->where('reference_code', $code)->exists());

        return $code;
    }
}
