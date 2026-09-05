<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * «پیشنهاد ویژه» — the band, the two prices and the burst on the photograph.
 *
 * **The migration this exercises is a no-op in every other test, and that is
 * why this file exists.** Migrations run against an empty database and the
 * catalogue is seeded afterwards, so a data migration that finds its product
 * by slug finds nothing and skips both of its writes. It is the right shape —
 * production is not a fresh install and the seeder cannot reach it — but it
 * means the only thing that ever runs these statements is the live deploy,
 * where a mistake is a wrong price in front of customers.
 *
 * So this seeds first and then calls `up()` itself, the way `DemoProductTest`
 * does with the migration that retires the test product.
 *
 * What it holds is the arithmetic the client asked for — «قیمت اصلیش ۴ میلیون
 * ۹۰۰ باشه که ۲۰ درصد تخفیف خورده» — in the unit the column is actually in,
 * which is the half that is easy to get wrong by a factor of ten.
 */
class SpecialOfferPriceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);

        app(TenantContext::class)->set(Branch::central());
    }

    private function migration(): Migration
    {
        return require database_path(
            'migrations/2026_09_05_140000_the_on_running_is_the_special_offer_at_twenty_off.php'
        );
    }

    public function test_the_band_becomes_the_on_running_at_the_asked_for_prices(): void
    {
        $this->migration()->up();

        $deal = $this->get('/')->assertOk()->viewData('dailyDeal');

        $this->assertSame('on-cloudtilt', $deal['product']->slug);

        $offer = $deal['product']->offerHere();

        // Rial in the column, Toman on the page. Asserted in both, because a
        // factor of ten is the mistake this is here to catch and only one of
        // the two units would show it.
        $this->assertSame(4_900_000 * 10, $offer->compare_at_price);
        $this->assertSame(3_920_000 * 10, $offer->price);
        $this->assertSame(fa_number(4_900_000), toman($offer->compare_at_price));
        $this->assertSame(fa_number(3_920_000), toman($offer->price));

        // And ٪۲۰ exactly, so the burst on the photograph says the number the
        // two prices beside it imply rather than a rounded neighbour of it.
        $this->assertSame(20, $offer->discountPercent());
    }

    /**
     * The band prints both numbers and draws the burst.
     *
     * Asserted on the rendered page rather than on the view data: the whole
     * point of the ask was what a customer sees, and a `@if` that never opens
     * would leave every assertion above passing.
     */
    public function test_the_page_shows_the_before_price_and_the_burst(): void
    {
        $this->migration()->up();

        $page = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(
            '<del>'.fa_number(4_900_000).'</del>',
            $page,
            'The band is not printing the price before the cut.'
        );

        $this->assertStringContainsString(
            'vp-daily-deal-shot"><svg class="vp-deal-burst"',
            $page,
            'The burst is not on the photograph.'
        );
    }

    /**
     * **With no discount there is no struck-through number and no burst.**
     *
     * This is the case that keeps the band honest, and it is the failure this
     * repository has already had once in another shape: a campaign that ends
     * leaves `compare_at_price` behind, and a band guarded on that column
     * alone would go on drawing a struck price and a ٪ badge over a price that
     * is no longer cut. Nothing would go red — see «the stepped sale has no
     * end date» in CLAUDE.md for the afternoon that cost.
     */
    public function test_it_prints_one_price_when_nothing_is_discounted(): void
    {
        $this->migration()->up();

        Product::query()->where('slug', 'on-cloudtilt')->firstOrFail()
            ->variants()->pluck('id')
            ->each(fn (int $id) => \DB::table('branch_offers')
                ->where('variant_id', $id)
                ->update(['compare_at_price' => null]));

        $page = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('vp-daily-deal-shot"><svg', $page);
        $this->assertStringContainsString('vp-daily-deal-price">'.fa_number(3_920_000), $page);
    }

    /**
     * **The cut-out, not the catalogue's own photograph.**
     *
     * «آن رانینگ تو هیرو یعنی مشکی سفیده بیاد اینجا» — the band was drawing the
     * product's studio shot, on the studio's ground, while the hero draws the
     * same shoe cut out. One shoe has one picture wherever the front page
     * draws it.
     *
     * The band reads the map the best sellers read, which is the point rather
     * than a shortcut: a second map keyed by the same slugs is how two lists
     * come apart with nothing to notice — this repository has already paid for
     * that once, with a duplicated hero tone map that drifted and put a black
     * shoe in a pink glow. So this asserts the band takes the mapped path, and
     * that it is genuinely not the product's own.
     */
    public function test_the_band_draws_the_shared_cut_out(): void
    {
        $product = $this->get('/')->assertOk()->viewData('dailyDeal')['product'];

        $cutout = config('storefront.placeholders.best_sellers.photos')[$product->slug] ?? null;

        $this->assertNotNull($cutout, 'The band\'s shoe has no cut-out, so this case is not testing anything.');
        $this->assertNotSame(
            $cutout,
            $product->imagePath(),
            'The cut-out and the catalogue photograph are the same file, so this case cannot fail.'
        );

        $this->get('/')->assertOk()->assertSee($cutout, false);
    }

    /**
     * **The whole card opens the product, through exactly one link.**
     *
     * «هرجای این کارت زده میشه باید بره به صفحه جزئیات خرید همین محصول» — done
     * by stretching the button's own hit area over the card rather than by
     * wrapping the card in a second anchor, because an <a> inside an <a> is
     * invalid and browsers repair it by splitting the outer one.
     *
     * The stretch itself is CSS and nothing here can see it. What this holds is
     * the half that would make the CSS meaningless: that the card contains one
     * anchor and no more, and that it goes to this product. A rewrite that adds
     * a second link inside the card is the failure this catches, and it would
     * otherwise look fine on screen right up until a click landed on the wrong
     * one.
     */
    public function test_the_card_carries_exactly_one_link_and_it_is_the_product(): void
    {
        $page = $this->get('/')->assertOk();

        $product = $page->viewData('dailyDeal')['product'];
        $html = $page->getContent();

        // `Str::between` runs to the *last* needle, which here is the last
        // `</section>` on the page — the whole footer and then some. The card
        // is the first close after the card's own opening tag.
        $card = Str::before(Str::after($html, '<div class="vp-daily-deal-card">'), '</section>');

        $this->assertSame(
            1,
            substr_count($card, '<a '),
            'The special offer card should hold one link and only one — see `.vp-daily-deal-cta::after`.'
        );

        $this->assertStringContainsString(storefront_route('product', $product), $card);

        // And it still says what it was asked to say, with the mark after the
        // words — «آیکون باید جلوی جمله باشه نه پشتش», the left end on an RTL
        // row.
        $this->assertStringContainsString(
            'اضافه کردن به سبد خرید<svg class="vp-daily-deal-mark"',
            $card
        );
    }
}
