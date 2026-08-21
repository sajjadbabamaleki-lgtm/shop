<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\DicePlay;
use App\Models\DiscountCode;
use App\Support\Branches\BranchOpener;
use App\Support\Game\DiceGame;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «تاس شانس» — the game band on the front page.
 *
 * The band is a picture; the game is these two classes. What matters is not
 * that the dice look right but that **the browser cannot decide anything**:
 * the request carries no body, the faces come from `random_int` on this side,
 * and the prize is a real row in `discount_codes` with a real limit on it.
 *
 * The thing most likely to go wrong quietly is the throw limit. A visitor who
 * can take one throw more than they are owed can take a hundred, and at 1 in
 * 36 that is a certainty rather than a game — so the rule is pinned from three
 * directions here: through the service, through the route, and through the
 * unique index when two throws land together.
 *
 * It is two throws now, not one («کاربر هم ۲ شانس داشته باشه نه یک شانس»),
 * which is why the limit is read from config in these tests rather than
 * written into them: the number is the shop's to change and the tests follow
 * it, but going *past* it must fail whatever it is set to.
 */
class DiceGameTest extends TestCase
{
    use RefreshDatabase;

    private Branch $central;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);

        $this->central = Branch::central();
        app(TenantContext::class)->set($this->central);

        config([
            'storefront.game.enabled' => true,
            // Honest dice unless a test says otherwise. `rig_attempt` is a
            // live switch in config and a suite that silently inherited it
            // would stop testing the game and start testing the rig.
            'storefront.game.rig_attempt' => null,
        ]);
    }

    private function game(): DiceGame
    {
        return app(DiceGame::class);
    }

    /**
     * Force the dice. `random_int` is not mockable, so a decided outcome is
     * made by writing the play the way the service would and letting the
     * one-throw rule hand it back.
     */
    private function playWith(string $key, int $first, int $second): DicePlay
    {
        $play = DicePlay::create([
            'player_key' => $key,
            'first_die' => $first,
            'second_die' => $second,
            'won' => $first === 6 && $second === 6,
        ]);

        if ($play->won) {
            // The award is private, and deliberately so — this reaches it the
            // only way anything else does, by playing.
            $play->delete();

            return $this->winFor($key);
        }

        return $play;
    }

    /** Throw until this key wins. One in 36, so this ends. */
    private function winFor(string $key): DicePlay
    {
        for ($i = 0; $i < 2000; $i++) {
            $play = $this->game()->play($key.':'.$i);

            if ($play->won) {
                DicePlay::where('id', $play->id)->update(['player_key' => $key]);

                return $play->fresh()->load('code');
            }
        }

        $this->fail('2000 throws and no double six — that is not luck, that is a bug.');
    }

    public function test_the_dice_are_thrown_on_the_server_and_the_request_carries_nothing(): void
    {
        // Whatever the page sends is ignored: the answer is two faces this
        // side chose, and a body claiming a win changes nothing.
        $answer = $this->postJson('/game/dice', ['dice' => [6, 6], 'won' => true])
            ->assertOk()
            ->json();

        $this->assertCount(2, $answer['dice']);

        foreach ($answer['dice'] as $face) {
            $this->assertGreaterThanOrEqual(1, $face);
            $this->assertLessThanOrEqual(6, $face);
        }

        $this->assertSame(
            $answer['dice'][0] === 6 && $answer['dice'][1] === 6,
            $answer['won'],
            'A win is a double six and nothing else.'
        );

        $play = DicePlay::firstOrFail();
        $this->assertSame([$play->first_die, $play->second_die], $answer['dice']);
    }

    public function test_a_visitor_gets_exactly_the_throws_config_promises(): void
    {
        $tries = (int) config('storefront.game.tries');
        $this->assertGreaterThan(1, $tries, 'This test is about the second throw.');

        $thrown = [];

        for ($i = 1; $i <= $tries; $i++) {
            $answer = $this->postJson('/game/dice')->assertOk()->json();

            $this->assertTrue($answer['fresh'], "Throw {$i} was not a fresh throw.");
            $thrown[] = $answer['dice'];

            // **The win is checked before the count, and that order matters.**
            // These are honest dice, so any throw here can come up a double
            // six — 1 in 36 — and winning ends the game with throws still in
            // hand. Asserting `left` first made this test fail about once in
            // every thirty-six runs: it passed locally and reddened CI, which
            // is the worst way for a flake to introduce itself.
            if ($answer['won']) {
                $this->assertSame(0, $answer['left'], 'A winner was left with throws in hand.');

                return;
            }

            $this->assertSame($tries - $i, $answer['left'], "Throw {$i} miscounted what is left.");
        }

        // Everything after that is a read-back of the last throw, however many
        // times it is asked for.
        for ($i = 0; $i < 5; $i++) {
            $again = $this->postJson('/game/dice')->assertOk()->json();

            $this->assertSame(end($thrown), $again['dice'], 'A throw past the limit invented new dice.');
            $this->assertFalse($again['fresh'], 'A read-back was reported as a fresh throw.');
            $this->assertSame(0, $again['left']);
        }

        $this->assertSame($tries, DicePlay::count());
        $this->assertSame(range(1, $tries), DicePlay::orderBy('attempt')->pluck('attempt')->all());
    }

    /**
     * The rig: a throw that wins on purpose.
     *
     * Temporary and asked for in those words — «فعلا دفعه دوم تستو جفت شیش
     * بزار برای همه». It is tested rather than left to a hand-check because
     * of what it costs while it is on: with `rig_attempt = 2`, *every* player
     * who throws twice takes 30% off, so the day it is meant to come back off
     * this test is what proves the switch actually turns it off.
     */
    public function test_a_rigged_throw_wins_for_everybody(): void
    {
        config(['storefront.game.rig_attempt' => 2]);

        // The first throw is written rather than thrown, so this test always
        // reaches the thing it is about. It used to throw honestly and skip
        // itself when that came up a double six — 1 in 36 — which is a test
        // that silently does not run, on a switch that is giving away 30%.
        $key = 's:'.str_repeat('r', 32);

        DicePlay::create([
            'player_key' => $key,
            'attempt' => 1,
            'first_die' => 1,
            'second_die' => 2,
            'won' => false,
        ]);

        $second = $this->withSession(['game.dice.key' => $key])
            ->postJson('/game/dice')->assertOk()->json();

        $this->assertSame([6, 6], $second['dice']);
        $this->assertTrue($second['won']);
        $this->assertNotNull($second['code'], 'A rigged win has to issue a real code.');
        $this->assertSame(0, $second['left']);
    }

    public function test_turning_the_rig_off_gives_the_dice_back(): void
    {
        config(['storefront.game.rig_attempt' => null]);

        $this->assertNull($this->game()->riggedAttempt());

        // Fifty second throws by fifty different people. Rigged, every one is
        // a double six; honest, all fifty together are about a 4% chance.
        $wins = 0;

        for ($i = 0; $i < 50; $i++) {
            $key = 's:honest'.$i;
            $this->game()->play($key);
            $wins += $this->game()->play($key)->won ? 1 : 0;
        }

        $this->assertLessThan(50, $wins, 'Every second throw won — the rig is still on.');
    }

    public function test_winning_ends_the_game_even_with_a_throw_in_hand(): void
    {
        $play = $this->winFor('s:early');

        $this->assertSame(1, $play->attempt, 'This test is about winning on the first throw.');
        $this->assertSame(0, $this->game()->throwsLeft('s:early'), 'A winner was offered another throw.');

        // And asking again hands back the prize rather than rolling again.
        $again = $this->game()->play('s:early');

        $this->assertSame($play->id, $again->id);
        $this->assertSame(1, DicePlay::where('player_key', 's:early')->count());
    }

    public function test_a_double_tap_never_buys_an_extra_throw(): void
    {
        $key = 's:'.str_repeat('a', 32);
        $tries = (int) config('storefront.game.tries');

        // Every throw the visitor is owed, and then several more from a hand
        // that will not stop tapping. The unique key on
        // (branch, player, attempt) is what settles it — a count in PHP would
        // be settled by whichever request read the table first.
        for ($i = 0; $i < $tries + 6; $i++) {
            if ($this->game()->play($key)->won) {
                break;
            }
        }

        $this->assertLessThanOrEqual(
            $tries,
            DicePlay::where('player_key', $key)->count(),
            'Tapping the button harder produced more throws than the shop offers.'
        );
    }

    public function test_a_signed_in_shopper_carries_their_throws_across_devices(): void
    {
        $customer = Customer::create(['name' => 'مینا', 'phone' => '09120000001']);
        $tries = (int) config('storefront.game.tries');
        $last = null;

        // Use them all up on one device.
        for ($i = 0; $i < $tries; $i++) {
            $last = $this->actingAs($customer, 'customer')->postJson('/game/dice')->assertOk()->json();
        }

        // A second visit, in a session that shares nothing with the first —
        // a new phone, a cleared browser. Signing in is what carries the
        // count; a session token could not.
        $this->flushSession();

        // Counted *before* the second visit rather than compared to `$tries`.
        // A natural double six on the first throw ends the game early and
        // leaves one row instead of two — a one-in-thirty-six failure that has
        // nothing to do with what this test is about, and it duly appeared.
        // What matters is that the second device adds nothing.
        $used = DicePlay::count();

        $again = $this->actingAs($customer, 'customer')->postJson('/game/dice')->assertOk()->json();

        $this->assertSame($last['dice'], $again['dice']);
        $this->assertFalse($again['fresh'], 'A new device handed a signed-in shopper another throw.');
        $this->assertSame(0, $again['left']);
        $this->assertSame($used, DicePlay::count(), 'The second device created a throw of its own.');
        $this->assertLessThanOrEqual($tries, $used);
        $this->assertSame($customer->id, DicePlay::first()->customer_id);
    }

    public function test_a_winners_code_is_their_own_once_and_for_a_day(): void
    {
        $play = $this->winFor('s:winner');

        $code = $play->code;
        $this->assertNotNull($code, 'A double six with no code is a prize nobody can spend.');

        $this->assertStringStartsWith('DICE-', $code->code);
        $this->assertSame('percent', $code->type);
        $this->assertSame(30 * 100, $code->value, 'Percent is stored in hundredths.');
        $this->assertSame(1, $code->usage_limit);
        $this->assertSame(1, $code->usage_limit_per_customer);
        $this->assertSame($this->central->id, $code->branch_id);
        $this->assertTrue($code->isLive());
        $this->assertEqualsWithDelta(
            now()->addHours(24)->timestamp,
            $code->ends_at->timestamp,
            60,
            'The card says 24 hours; the code has to agree with the card.'
        );
    }

    public function test_two_winners_do_not_share_a_code(): void
    {
        $one = $this->winFor('s:one');
        $two = $this->winFor('s:two');

        $this->assertNotSame($one->code->code, $two->code->code);
    }

    public function test_a_code_that_has_run_out_of_time_is_not_handed_back(): void
    {
        $play = $this->winFor('s:stale');
        // `discount_codes_window_ordered` refuses an end before its start,
        // so the whole window moves back rather than just the end.
        $play->code->update([
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);

        $answer = $this->game()->play('s:stale');
        $this->assertTrue($answer->won);
        $this->assertFalse($answer->code->fresh()->isLive());

        // And the route says so rather than printing a code the basket would
        // refuse — the page has a sentence for exactly this.
        $this->withSession(['game.dice.key' => 's:stale'])
            ->postJson('/game/dice')
            ->assertOk()
            ->assertJson(['won' => true, 'code' => null]);
    }

    public function test_switching_the_game_off_takes_the_band_and_the_route_with_it(): void
    {
        config(['storefront.game.enabled' => false]);

        $this->get('/')->assertOk()->assertDontSee('vp-dice-area', false);
        $this->postJson('/game/dice')->assertNotFound();
        $this->assertSame(0, DicePlay::count());
    }

    public function test_the_band_is_on_the_front_page_when_the_game_is_on(): void
    {
        $page = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('vp-dice-area', $page);
        $this->assertStringContainsString('data-dice-go', $page);
        $this->assertStringContainsString('data-dice-token', $page);
        $this->assertStringContainsString('/game/dice', $page);

        // The band may not promise a number of throws the game does not give.
        $this->assertStringContainsString(
            'هر کاربر '.fa_number(config('storefront.game.tries')).' بار شانس داره',
            $page
        );

        // The result is never in the markup — every visitor is served the same
        // opening state, which is also why the parity check can compare this
        // page with the static preview at all. (`vp-prize` itself *is* in the
        // page: the script names the classes it will build. A code is not.)
        $this->assertStringNotContainsString('DICE-', $page);
        $this->assertStringNotContainsString('class="vp-dice-done"', $page);
        $this->assertStringContainsString('data-dice-pair', $page);
    }

    public function test_a_prize_belongs_to_the_branch_it_was_won_at(): void
    {
        $shiraz = app(BranchOpener::class)
            ->open('shiraz', 'شیراز', markupPercent: 0, openingStock: 3);

        app(TenantContext::class)->set($shiraz);
        $play = $this->winFor('s:shirazi');

        $this->assertSame($shiraz->id, $play->code->branch_id);

        // And the central shop's basket cannot spend it.
        app(TenantContext::class)->set($this->central);
        $this->assertNull(
            DiscountCode::query()->usableAt($this->central->id)->where('code', $play->code->code)->first()
        );
    }
}
