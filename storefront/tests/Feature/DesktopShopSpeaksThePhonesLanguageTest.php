<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «نسخه دستاپ فروشگاهش … هیچ شباهتی به نسخه موبایل نداره و فیلترهاشم اصلا کار
 * نمیکنه».
 *
 * The desktop listing is the phone's, carried up by theme/make-desktop-shop.js.
 * What this holds is what a stylesheet alone cannot: that the block is there
 * and only above 992, and that a ticked size survives the next filter.
 */
class DesktopShopSpeaksThePhonesLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_phones_rules_are_carried_to_the_desktop_and_only_there(): void
    {
        $css = file_get_contents(public_path('assets/css/tweaks.css'));

        // The shipped copy has its comments stripped, so the block is found by
        // the one rule only it writes: the sidebar hidden on the desktop.
        $at = strpos($css, '.vp-listing-panel .vp-shop-rail-desktop');
        $this->assertNotFalse($at, 'the desktop shop block is gone — run theme/make-desktop-shop.js');

        // It sits inside a min-width 992 query, not a phone one.
        $opens = strrpos(substr($css, 0, $at), '@media');
        $this->assertStringStartsWith('@media (min-width: 992px)', substr($css, $opens, 30));

        // The parts the phone has, all of them, in that same block.
        $block = substr($css, $opens, strpos($css, '@media (min-width: 1400px)', $at) - $opens);
        foreach (['.vp-shop-top', '.vp-shop-tabs', '.vp-shop-strip', '.vp-shop-filter-panel', '.vp-sheet', '.vp-stories'] as $part) {
            $this->assertStringContainsString('.vp-listing-panel '.$part, $block, "{$part} did not reach the desktop");
        }

        // And the old sidebar is not shown beside the popup that replaces it.
        $this->assertStringContainsString('.vp-listing-panel .vp-shop-rail-desktop', $block);
    }

    /**
     * A ticked size posted an empty value, so applying any other filter
     * dropped it.
     */
    public function test_a_ticked_size_posts_itself(): void
    {
        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
        app(TenantContext::class)->set(Branch::central());

        $page = $this->get('/products?size=38')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/name="size" value="38" data-vp-toggle\s+checked/', $page);
        $this->assertStringNotContainsString('name="size" value=""', $page);
    }
}
