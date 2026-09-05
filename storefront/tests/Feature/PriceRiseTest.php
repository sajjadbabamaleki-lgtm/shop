<?php

namespace Tests\Feature;

use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ۳۰۰٬۰۰۰ تومان on every price in the shop, and what it must not break.
 *
 * «یکاری کن ۳۰۰ هزارتومن به قیمت فعلی همه کفش ها اضافه کن.»
 *
 * **The migration itself cannot be tested where it runs.** `migrate:fresh
 * --seed` runs every migration before the seeder, so on this database it meets
 * an empty `branch_offers` and raises nothing — which is correct, and is also
 * why the seeded prices in every other test are untouched. What it does on the
 * live shop, where the table is full, is what these cases stand in for: the
 * same two statements, run by hand against a seeded catalogue.
 *
 * Three things have to hold, and each of them is money if it does not.
 */
class PriceRiseTest extends TestCase
{
    use RefreshDatabase;

    /** ۳۰۰٬۰۰۰ تومان, in the Rial this application counts in. */
    private const RISE = 300_000 * 10;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
    }

    private function raise(): void
    {
        DB::table('branch_offers')->update([
            'price' => DB::raw('price + '.self::RISE),
            'compare_at_price' => DB::raw('compare_at_price + '.self::RISE),
        ]);
    }

    /** Every price, in every branch, by exactly that much. */
    public function test_every_price_rises_by_three_hundred_thousand_toman(): void
    {
        $before = DB::table('branch_offers')->orderBy('id')->pluck('price', 'id');

        $this->assertNotEmpty($before, 'no offers, so this test is not testing anything');

        $this->raise();

        foreach (DB::table('branch_offers')->orderBy('id')->pluck('price', 'id') as $id => $after) {
            $this->assertSame($before[$id] + self::RISE, $after, "offer {$id} did not rise by ۳۰۰٬۰۰۰ تومان");
        }
    }

    /**
     * The «was» price rises with it.
     *
     * Left still, every discount on the site would quietly shrink by ۳۰۰٬۰۰۰ —
     * a change to the campaign nobody asked for — and a shoe whose two prices
     * are within that of each other would break the table's own CHECK, which
     * requires `compare_at_price >= price`.
     */
    public function test_the_struck_through_price_rises_too(): void
    {
        $before = DB::table('branch_offers')
            ->whereNotNull('compare_at_price')
            ->orderBy('id')
            ->pluck('compare_at_price', 'id');

        $this->assertNotEmpty($before, 'nothing is discounted, so this test is not testing anything');

        $this->raise();

        foreach ($before as $id => $was) {
            $now = DB::table('branch_offers')->where('id', $id)->value('compare_at_price');

            $this->assertSame($was + self::RISE, $now, "offer {$id}'s «was» price did not follow");
        }
    }

    /** A shoe with no «was» price still has none — NULL + n is NULL. */
    public function test_an_undiscounted_offer_gains_no_struck_through_price(): void
    {
        DB::table('branch_offers')->update(['compare_at_price' => null]);

        $this->raise();

        $this->assertSame(
            0,
            DB::table('branch_offers')->whereNotNull('compare_at_price')->count(),
            'the rise invented a «was» price for a shoe that had none'
        );
    }

    /** And what the shop paid did not change because it decided to charge more. */
    public function test_the_cost_price_is_left_alone(): void
    {
        $before = DB::table('branch_offers')->orderBy('id')->pluck('cost_price', 'id');

        $this->raise();

        $this->assertEquals($before, DB::table('branch_offers')->orderBy('id')->pluck('cost_price', 'id'));
    }
}
