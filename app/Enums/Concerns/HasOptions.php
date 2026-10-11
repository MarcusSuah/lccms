<?php

namespace App\Enums\Concerns;

/**
 * Shared helpers for string-backed enums that expose a human readable label.
 */
trait HasOptions
{
    /**
     * Options suitable for select inputs on the frontend.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }

    /**
     * All backing values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
