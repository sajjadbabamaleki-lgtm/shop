<?php

namespace App\Support\Game;

use App\Models\DicePlay;
use App\Models\DiscountCode;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * «تاس شانس» — the throw, and the prize behind it.
 *
 * **The dice are thrown here, on the server.** Not in the page. A game whose
 * outcome is decided in JavaScript is a discount code anybody can award
 * themselves by opening the console, and it would be awarded by exactly the
 * people least likely to have bought anything. The page's animation is a
 * picture of a result that has already happened.
 *
 * **The prize is a real code, made per winner.** The design shows one shared
 * `DICE66`; this issues `DICE-XXXXXX`, different every time, limited to a
 * single use and expiring in a day. The reason is not tidiness: one shared
 * code is one screenshot away from a Telegram channel, and then every visitor
 * has 30% off whether they played or not — which is the opposite of a game.
 * The card looks the same either way; only the letters differ. Say so if the
 * client wants the shared one back, because it is their money.
 *
 * **Two throws, and winning ends it.** «کاربر هم ۲ شانس داشته باشه نه یک
 * شانس» — so `tries` is 2, and a person who wins on the first does not throw
 * again: the prize is the prize. Two throws at 1 in 36 apiece is about 5 or 6
 * winners in a hundred rather than 3.
 *
 * Every number that decides anything is in `config('storefront.game')` and
 * none of them are in here.
 */
class DiceGame
{
    public function __construct(private TenantContext $tenant) {}

    public function isOn(): bool
    {
        return (bool) config('storefront.game.enabled', false);
    }

    /** How many throws one person gets. */
    public function tries(): int
    {
        return max(1, (int) config('storefront.game.tries', 1));
    }

    /** Everything this person has thrown, oldest first. */
    public function playsFor(string $playerKey): Collection
    {
        return DicePlay::where('player_key', $playerKey)
            ->with('code')->orderBy('attempt')->get();
    }

    /** Their last throw, if they have had one. */
    public function playFor(string $playerKey): ?DicePlay
    {
        return $this->playsFor($playerKey)->last();
    }

    /**
     * How many throws this person has left. Winning ends the game — the prize
     * is the prize, and a second code for the same person is a second code.
     */
    public function throwsLeft(string $playerKey): int
    {
        $plays = $this->playsFor($playerKey);

        if ($plays->contains(fn (DicePlay $p) => $p->won)) {
            return 0;
        }

        return max(0, $this->tries() - $plays->count());
    }

    /**
     * Throw two dice and settle up.
     *
     * Returns the play. A call with nothing left does not throw again — it
     * returns what they already got, so a refresh or a double tap shows the
     * same result rather than a new one.
     */
    public function play(string $playerKey, ?int $customerId = null): DicePlay
    {
        $plays = $this->playsFor($playerKey);
        $won = $plays->first(fn (DicePlay $p) => $p->won);

        if ($won !== null) {
            return $won;
        }

        if ($plays->count() >= $this->tries()) {
            return $plays->last();
        }

        $attempt = $plays->count() + 1;

        // `random_int`, not `rand`: this decides money, and a predictable
        // sequence is a prize somebody can wait for.
        $first = random_int(1, 6);
        $second = random_int(1, 6);

        // …unless this throw is the rigged one. See `rig_attempt` in
        // config/storefront.php: it is a deliberate, temporary switch that
        // hands *every* player the prize on a given throw, and the comment
        // there says what it costs.
        if ($this->riggedAttempt() === $attempt) {
            $first = 6;
            $second = 6;
        }

        $double = $first === 6 && $second === 6;

        try {
            return DB::transaction(function () use ($playerKey, $customerId, $attempt, $first, $second, $double): DicePlay {
                $play = DicePlay::create([
                    'customer_id' => $customerId,
                    'player_key' => $playerKey,
                    'attempt' => $attempt,
                    'first_die' => $first,
                    'second_die' => $second,
                    'won' => $double,
                ]);

                if ($double) {
                    $play->update(['discount_code_id' => $this->awardCode()->id]);
                }

                return $play->load('code');
            });
        } catch (QueryException $e) {
            // Two taps arriving together: the unique index refused the second,
            // which is the point of having it. The key carries the attempt
            // number, so this holds on throw two exactly as it did on throw
            // one. Whichever landed first is the answer, and this reads it
            // back rather than inventing another.
            $play = $this->playFor($playerKey);

            if ($play === null) {
                throw $e;
            }

            return $play;
        }
    }

    /** Which throw, if any, is fixed to win. Null when the dice are honest. */
    public function riggedAttempt(): ?int
    {
        $attempt = (int) config('storefront.game.rig_attempt', 0);

        return $attempt > 0 ? $attempt : null;
    }

    /**
     * The winner's code: theirs, once, for a day.
     *
     * Made against the branch the game was played at, so a franchise's prize
     * is spendable at that franchise. `usage_limit_per_customer` is set as
     * well as `usage_limit` — belt and braces for the case where a customer id
     * exists, since `usage_limit` alone is what a shared link would exhaust.
     */
    private function awardCode(): DiscountCode
    {
        $game = config('storefront.game');

        return DiscountCode::create([
            'code' => 'DICE-'.Str::upper(Str::random(6)),
            'description' => 'جایزه تاس شانس',
            'type' => 'percent',
            // Percent is stored in hundredths, the way the rest of the
            // application does it: 30% is 3000.
            'value' => (int) $game['percent'] * 100,
            'branch_id' => $this->tenant->id(),
            'min_subtotal' => (int) ($game['min_subtotal_rial'] ?? 0),
            'max_discount' => ((int) ($game['max_discount_rial'] ?? 0)) ?: null,
            'starts_at' => now(),
            'ends_at' => now()->addHours((int) $game['hours']),
            'usage_limit' => 1,
            'usage_limit_per_customer' => 1,
            'is_active' => true,
        ]);
    }
}
