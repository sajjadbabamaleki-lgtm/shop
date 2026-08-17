<?php

namespace App\Support\Fulfilment;

use App\Models\Branch;

/**
 * A branch's §10 settings, with every key present.
 *
 * The reason this is a class and not `$branch->fulfilment` read directly: the
 * column is nullable and the document grows. Every caller would otherwise
 * write its own `?? 2`, and the day somebody changes the default preparation
 * time they would have to find all of them — which is how the admin screen and
 * the calculation end up disagreeing about what «unset» means.
 */
class FulfilmentSettings
{
    /** What a branch that has decided nothing still gets. */
    public const DEFAULTS = [
        'preparation_days' => 2,
        // ISO-8601 weekdays: Saturday 6, Sunday 7, Monday 1 … Friday 5. The
        // ordinary Iranian week is Saturday to Wednesday.
        'working_days' => [6, 7, 1, 2, 3],
        'holidays' => [],
        'cutoff' => '14:00',
        'max_delay_days' => 7,
        'delays_enabled' => true,
    ];

    /** @return array<string, mixed> */
    public static function for(?Branch $branch): array
    {
        $stored = $branch?->fulfilment;

        if (! is_array($stored)) {
            return self::DEFAULTS;
        }

        return array_replace(self::DEFAULTS, array_filter(
            $stored,
            fn ($value) => $value !== null,
        ));
    }
}
