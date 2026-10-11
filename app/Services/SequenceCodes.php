<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Human-readable sequential reference codes such as LR-MO-…-C001-F003.
 * Uses a named database lock so concurrent creations never collide; the
 * unique index on reference_code remains the final guarantee.
 */
class SequenceCodes
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public function next(string $modelClass, string $prefix, int $width = 3): string
    {
        $lockName = substr('seq:'.md5($modelClass.$prefix), 0, 60);
        DB::select('SELECT GET_LOCK(?, 10)', [$lockName]);

        try {
            $sequence = $modelClass::query()->where('reference_code', 'like', $prefix.'%')->count() + 1;

            do {
                $code = $prefix.str_pad((string) $sequence++, $width, '0', STR_PAD_LEFT);
            } while ($modelClass::query()->where('reference_code', $code)->exists());

            return $code;
        } finally {
            DB::select('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }
}
