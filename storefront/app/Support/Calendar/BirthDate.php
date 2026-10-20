<?php

namespace App\Support\Calendar;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * A birth date as the forms ask for it: three Persian selects — day, month,
 * year — read back into one date.
 *
 * Three selects and not a date input: a browser draws `type="date"` in the
 * device's own calendar, which on most telephones here is the Gregorian one,
 * and nobody knows their birthday in it.
 */
final class BirthDate
{
    public const FIELDS = ['birth_day', 'birth_month', 'birth_year'];

    /**
     * The date the three fields name; null when all three are empty and
     * `$required` is false. Anything half-filled or impossible is refused
     * under `birth_date`, the key the form prints its sentence under.
     */
    public static function fromRequest(Request $request, bool $required): ?CarbonImmutable
    {
        $parts = array_map(fn (string $f) => (int) latin_digits((string) $request->input($f, '')), self::FIELDS);

        if ($parts === [0, 0, 0] && ! $required) {
            return null;
        }

        [$day, $month, $year] = $parts;
        $thisYear = Jalali::thisYear();

        $date = ($year >= 1300 && $year <= $thisYear) ? Jalali::toDate($year, $month, $day) : null;

        if ($date === null || $date->isFuture()) {
            throw ValidationException::withMessages([
                'birth_date' => $parts === [0, 0, 0] ? 'تاریخ تولدت را انتخاب کن.' : 'این تاریخ تولد درست نیست.',
            ]);
        }

        return $date;
    }
}
