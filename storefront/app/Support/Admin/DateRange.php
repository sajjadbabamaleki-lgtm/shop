<?php

namespace App\Support\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The window a screen is reporting on, and the one before it.
 *
 * §3: «Date controls: Today / Yesterday / 7 Days / 30 Days / 90 Days / Custom»
 * and «Every KPI should support comparison against the previous equivalent
 * period». The comparison is the reason this is a class rather than two
 * `whereBetween`s in a controller: «the previous equivalent period» has to be
 * the same *length* immediately before, and computing that at each call site is
 * how one card ends up comparing thirty days against a month.
 *
 * The window is closed at both ends and inclusive of both, so a single day is
 * 00:00:00 to 23:59:59 of that day and «today» does not silently include part
 * of tomorrow in a timezone that is not the shop's. The application's timezone
 * is Asia/Tehran, set in config, and every boundary here is taken from
 * `now()`, which honours it.
 */
class DateRange
{
    /** @var array<string, int> preset => days it spans */
    public const PRESETS = [
        'today' => 1,
        'yesterday' => 1,
        '7' => 7,
        '30' => 30,
        '90' => 90,
    ];

    /** @var array<string, string> */
    public const LABELS = [
        'today' => 'امروز',
        'yesterday' => 'دیروز',
        '7' => '۷ روز',
        '30' => '۳۰ روز',
        '90' => '۹۰ روز',
        'custom' => 'دلخواه',
    ];

    public readonly CarbonImmutable $from;

    public readonly CarbonImmutable $to;

    public readonly CarbonImmutable $previousFrom;

    public readonly CarbonImmutable $previousTo;

    public readonly string $preset;

    private function __construct(CarbonImmutable $from, CarbonImmutable $to, string $preset)
    {
        $this->from = $from->startOfDay();
        $this->to = $to->endOfDay();
        $this->preset = $preset;

        // The same number of days, ending the instant before this window opens.
        // Counted in days rather than by subtracting a duration, because a
        // window that crosses a daylight change is still the same number of
        // days and the comparison would otherwise be an hour short.
        $days = $this->days();

        $this->previousTo = $this->from->subSecond();
        $this->previousFrom = $this->from->subDays($days)->startOfDay();
    }

    /** How many days the window spans, counting both ends. */
    public function days(): int
    {
        return (int) $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;
    }

    /**
     * Read the window off the request.
     *
     * Anything unrecognised falls back to 30 days rather than erroring: this is
     * a query string, and a query string is something a person can type. A
     * dashboard that 500s because somebody trimmed a URL is worse than one that
     * shows the default month.
     */
    public static function fromRequest(Request $request): self
    {
        $preset = (string) $request->query('range', '30');

        if ($preset === 'custom') {
            $from = self::parse($request->query('from'));
            $to = self::parse($request->query('to'));

            if ($from && $to) {
                // Typed the wrong way round is a mistake, not an empty report.
                if ($from->greaterThan($to)) {
                    [$from, $to] = [$to, $from];
                }

                return new self($from, $to, 'custom');
            }
        }

        $today = CarbonImmutable::now();

        return match ($preset) {
            'today' => new self($today, $today, 'today'),
            'yesterday' => new self($today->subDay(), $today->subDay(), 'yesterday'),
            '7', '90' => new self($today->subDays((int) $preset - 1), $today, $preset),
            default => new self($today->subDays(29), $today, '30'),
        };
    }

    private static function parse(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The query string that reproduces this window — what every link on the
     * screen has to carry, or tapping a tab throws the date away.
     *
     * @return array<string, string>
     */
    public function carry(): array
    {
        if ($this->preset !== 'custom') {
            return ['range' => $this->preset];
        }

        return [
            'range' => 'custom',
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
        ];
    }

    public function label(): string
    {
        return self::LABELS[$this->preset] ?? self::LABELS['30'];
    }
}
