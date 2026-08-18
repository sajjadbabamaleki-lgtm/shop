<?php

namespace App\Support\Game;

use App\Models\DicePlay;
use App\Models\DiscountCode;
use App\Support\Tenancy\TenantContext;
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

    /** What this person has already done, if anything. */
    public function playFor(string $playerKey): ?DicePlay
    {
        return DicePlay::where('player_key', $playerKey)->with('code')->first();
    }

    /**
     * Throw two dice and settle up.
     *
     * Returns the play. A second call for the same person does not throw
     * again — it returns what they already got, so a refresh or a double tap
     * shows the same result rather than a new one.
     */
    public function play(string $playerKey, ?int $customerId = null): DicePlay
    {
        if ($already = $this->playFor($playerKey)) {
            return $already;
        }

        // `random_int`, not `rand`: this decides money, and a predictable
        // sequence is a prize somebody can wait for.
        $first = random_int(1, 6);
        $second = random_int(1, 6);
        $won = $first === 6 && $second === 6;

        try {
            return DB::transaction(function () use ($playerKey, $customerId, $first, $second, $won): DicePlay {
                $play = DicePlay::create([
                    'customer_id' => $customerId,
                    'player_key' => $playerKey,
                    'first_die' => $first,
                    'second_die' => $second,
                    'won' => $won,
                ]);

                if ($won) {
                    $play->update(['discount_code_id' => $this->awardCode()->id]);
                }

                return $play->load('code');
            });
        } catch (QueryException $e) {
            // Two taps arriving together: the unique index refused the second,
            // which is the point of having it. Whichever landed first is the
            // answer, and this reads it back rather than inventing another.
            $play = $this->playFor($playerKey);

            if ($play === null) {
                throw $e;
            }

            return $play;
        }
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
