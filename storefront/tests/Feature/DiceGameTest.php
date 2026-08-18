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
 * The thing most likely to go wrong quietly is the one-throw rule. A visitor
 * who can throw twice can throw a hundred times, and at 1 in 36 that is a
 * certainty rather than a game — so the rule is pinned from three directions
 * here: through the service, through the route, and through the unique index
 * when two throws land together.
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

        config(['storefront.game.enabled' => true]);
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

    public function test_one_throw_per_visitor(): void
    {
        $first = $this->postJson('/game/dice')->assertOk()->json();

        $this->assertTrue($first['fresh']);

        for ($i = 0; $i < 5; $i++) {
            $again = $this->postJson('/game/dice')->assertOk()->json();

            $this->assertSame($first['dice'], $again['dice'], 'A second throw invented new dice.');
            $this->assertFalse($again['fresh'], 'A read-back was reported as a fresh throw.');
        }

        $this->assertSame(1, DicePlay::count());
    }

    public function test_two_taps_arriving_together_are_still_one_throw(): void
    {
        // The service is asked twice for the same key with no play in between,
        // which is what a double tap looks like once both requests are past
        // the read. The unique index is what settles it.
        $key = 's:'.str_repeat('a', 32);

        $one = $this->game()->play($key);
        $two = $this->game()->play($key);

        $this->assertSame($one->id, $two->id);
        $this->assertSame(1, DicePlay::where('player_key', $key)->count());
    }

    public function test_a_signed_in_shopper_gets_one_throw_across_devices(): void
    {
        $customer = Customer::create(['name' => 'مینا', 'phone' => '09120000001']);

        $first = $this->actingAs($customer, 'customer')->postJson('/game/dice')->assertOk()->json();

        // A second visit, in a session that shares nothing with the first.
        $this->flushSession();

        $again = $this->actingAs($customer, 'customer')->postJson('/game/dice')->assertOk()->json();

        $this->assertSame($first['dice'], $again['dice']);
        $this->assertFalse($again['fresh']);
        $this->assertSame(1, DicePlay::count());
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
