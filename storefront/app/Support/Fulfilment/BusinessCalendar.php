<?php

namespace App\Support\Fulfilment;

use Carbon\CarbonImmutable;

/**
 * Working days — §3's «reusable business-calendar service rather than adding
 * raw calendar days».
 *
 * The rule this exists to enforce is short: **two working days after Wednesday
 * is not Friday.** Every date the shop promises a customer is counted in days
 * the shop is actually open, and the moment one place in the application adds
 * `->addDays(2)` instead, the admin screen and the SMS and the customer's own
 * order page start disagreeing about the same parcel. §20 puts it as «a single
 * source of truth for status, dates and events so that Admin, Customer, SMS and
 * real-time interfaces never disagree» — this is the dates half of that.
 *
 * It is deliberately given its rules rather than reading them: a calendar that
 * fetched the branch's settings itself could not be used to recompute an old
 * order against the rules that were in force when it was promised, and §10
 * says «do not silently rewrite historical promises».
 *
 * Weekdays are ISO-8601 numbers, so Saturday is 6 and Friday is 5 — the same
 * numbers `CarbonImmutable::dayOfWeekIso` gives, so nothing has to be
 * translated between here and a date.
 */
class BusinessCalendar
{
    /**
     * @param  list<int>  $workingDays  ISO-8601 weekday numbers the shop is open
     * @param  list<string>  $holidays  Y-m-d dates the shop is closed anyway
     */
    public function __construct(
        private readonly array $workingDays,
        private readonly array $holidays = [],
    ) {}

    /**
     * Build one from a branch's stored settings, falling back to a sane week.
     *
     * @param  array<string, mixed>  $settings
     */
    public static function fromSettings(array $settings): self
    {
        $days = $settings['working_days'] ?? [];

        return new self(
            // A branch that has somehow been left with no working days at all
            // would make every loop below run forever. The ordinary Iranian
            // week is the floor, and it is a floor rather than an exception
            // because a wrong-but-finite answer beats a hung request.
            workingDays: $days === [] ? [6, 7, 1, 2, 3] : array_values(array_map('intval', $days)),
            holidays: array_values(array_map('strval', $settings['holidays'] ?? [])),
        );
    }

    public function isWorkingDay(CarbonImmutable $day): bool
    {
        if (in_array($day->toDateString(), $this->holidays, true)) {
            return false;
        }

        return in_array($day->dayOfWeekIso, $this->workingDays, true);
    }

    /** The first working day on or after this one. */
    public function nextWorkingDay(CarbonImmutable $from): CarbonImmutable
    {
        $day = $from->startOfDay();

        // A year is the guard against a configuration that closes the shop
        // every day of the week — it cannot happen through the settings screen,
        // but a hand-edited row should still fail loudly rather than hang.
        for ($i = 0; $i <= 366; $i++) {
            if ($this->isWorkingDay($day)) {
                return $day;
            }

            $day = $day->addDay();
        }

        throw new \RuntimeException('This branch has no working day in the next year — check its calendar.');
    }

    /**
     * Add working days, counting from the first working day on or after the
     * start.
     *
     * «Two working days from Wednesday» means Wednesday counts as day zero and
     * the answer is the second working day after it, so with a Thursday and
     * Friday closed the answer is Sunday. Counting the start day as one is the
     * other convention and it is off by a day for every order — hence the test
     * that spells this out with a named example.
     */
    public function addWorkingDays(CarbonImmutable $from, int $days): CarbonImmutable
    {
        $day = $this->nextWorkingDay($from);

        for ($added = 0; $added < $days; $added++) {
            $day = $this->nextWorkingDay($day->addDay());
        }

        return $day;
    }

    /** How many working days lie between two dates, both ends counted. */
    public function workingDaysBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        if ($to->lessThan($from)) {
            return 0;
        }

        $count = 0;

        for ($day = $from->startOfDay(); $day->lessThanOrEqualTo($to->startOfDay()); $day = $day->addDay()) {
            if ($this->isWorkingDay($day)) {
                $count++;
            }
        }

        return $count;
    }
}
