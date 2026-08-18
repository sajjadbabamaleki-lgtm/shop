<?php

namespace App\Http\Controllers;

use App\Models\DicePlay;
use App\Support\Game\DiceGame;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The throw, from the page's side.
 *
 * One route, answering JSON, because the band is a small piece of a page that
 * is otherwise entirely server-rendered — a form post would reload the whole
 * front page to show two dice.
 *
 * **Nothing the browser sends decides anything.** The request carries no body
 * at all: who is playing comes from the session and the signed-in customer,
 * and what they rolled comes from `random_int` on this side. There is nothing
 * to tamper with because there is nothing being read.
 */
class DiceGameController extends Controller
{
    public function roll(Request $request, DiceGame $game): JsonResponse
    {
        if (! $game->isOn()) {
            return response()->json(['error' => 'بازی الان فعال نیست.'], 404);
        }

        $customer = $request->user('customer');

        $play = $game->play($this->playerKey($request, $customer?->id), $customer?->id);

        return response()->json($this->describe($play));
    }

    /**
     * Who is throwing.
     *
     * A signed-in shopper is their customer id, so the same person gets one
     * throw whatever device they use. Everybody else gets a random token kept
     * in the session — which means clearing cookies buys another throw, and
     * that is stated on the band rather than hidden. The prize is capped by
     * the code it issues (one use, one day), which is the limit that holds.
     */
    private function playerKey(Request $request, ?int $customerId): string
    {
        if ($customerId !== null) {
            return 'c:'.$customerId;
        }

        $key = $request->session()->get('game.dice.key');

        if (! is_string($key) || $key === '') {
            $key = 's:'.Str::random(32);
            $request->session()->put('game.dice.key', $key);
        }

        return $key;
    }

    /** @return array<string, mixed> */
    private function describe(DicePlay $play): array
    {
        return [
            'dice' => [$play->first_die, $play->second_die],
            'won' => $play->won,
            // Only while it is still worth typing. A returning winner whose
            // day has run out is told that, rather than handed a code the
            // basket will refuse.
            'code' => $play->code?->isLive() ? $play->code->code : null,
            'percent' => (int) config('storefront.game.percent'),
            'hours' => (int) config('storefront.game.hours'),
            // Whether this is the throw that just happened or one being read
            // back — the page says «قبلاً بازی کردی» rather than replaying the
            // animation as though it were new.
            'fresh' => $play->wasRecentlyCreated,
        ];
    }
}
