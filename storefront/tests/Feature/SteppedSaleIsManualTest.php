<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchOffer;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The stepped sale ends when somebody ends it, and not before.
 *
 * «بازه حراج پله ای نباید اوتومات بسته بشه باید همچیزش دستی باشه».
 *
 * The seeder used to open the window a week back and three weeks forward. Four
 * weeks after the shop went up it closed on its own; `hasActivePromotion()`
 * counts a discount only inside its window, so every offer stopped being a
 * promotion in the same minute, and the front page's three sale-fed bands went
 * with it.
 *
 * **No test could have caught that, and this file is the answer to why.** Every
 * other test seeds its catalogue seconds before it renders a page, so the
 * window was always open in one — the failure lived entirely in the gap between
 * when a database was seeded and when somebody looked at the site. So these
 * tests do the one thing the rest cannot: they move the clock.
 */
class SteppedSaleIsManualTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);

        app(TenantContext::class)->set(Branch::central());
    }

    /** A fresh install builds no countdown. */
    public function test_a_seeded_sale_carries_no_window_at_all(): void
    {
        $discounted = BranchOffer::query()
            ->whereNotNull('compare_at_price')
            ->whereColumn('compare_at_price', '>', 'price')
            ->get();

        $this->assertNotEmpty($discounted, 'The seeder stopped discounting anything; this test proves nothing now.');

        foreach ($discounted as $offer) {
            $this->assertNull(
                $offer->promotion_ends_at,
                'A seeded sale must not close by itself. See stop_the_stepped_sale_closing_itself.'
            );
            $this->assertNull($offer->promotion_starts_at);
        }
    }

    /**
     * A year later, with nobody having touched anything, the shop still has a
     * sale on and the front page is still whole. This is the exact test that
     * would have caught the failure, and it fails against the old seeder.
     */
    public function test_the_sale_is_still_running_a_year_later(): void
    {
        Carbon::setTestNow(now()->addYear());

        try {
            $offer = BranchOffer::query()
                ->whereNotNull('compare_at_price')
                ->whereColumn('compare_at_price', '>', 'price')
                ->first();

            $this->assertTrue($offer->hasActivePromotion(), 'The sale closed on its own after a year.');

            $this->assertSame(
                BranchOffer::query()->promoted()->count(),
                BranchOffer::query()
                    ->whereNotNull('compare_at_price')
                    ->whereColumn('compare_at_price', '>', 'price')
                    ->count(),
                'The listing and the badge disagree about what is on sale — see BranchOffer::scopePromoted().'
            );

            $page = $this->get('/')->assertOk();

            $this->assertNotEmpty($page->viewData('heroSlides'));
            $this->assertNotEmpty($page->viewData('ladderDeals'));
            $this->assertNotEmpty($page->viewData('bestSellers'));
            $this->assertNotNull($page->viewData('dailyDeal'));
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Manual is the other half: a window that somebody *does* set is still
     * obeyed. Taking the clock off must not mean taking the feature out — a
     * timed campaign is a thing the shop may want, it is simply no longer what
     * it gets without asking.
     */
    public function test_a_window_somebody_sets_by_hand_is_still_obeyed(): void
    {
        $offer = BranchOffer::query()
            ->whereNotNull('compare_at_price')
            ->whereColumn('compare_at_price', '>', 'price')
            ->first();

        $this->assertTrue($offer->hasActivePromotion());

        $offer->update(['promotion_ends_at' => now()->subDay()]);

        $this->assertFalse($offer->fresh()->hasActivePromotion());
    }
}
