<?php

namespace Tests\Feature;

use App\Models\Category;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The four sections that are announced but not open yet.
 *
 * «اکسسوری / ست کیف و کفش / ست ورزشی / بوت و نیم بوت — اینا باید هرجا روشون
 * زده بشه باید بشن کامینگ سون». They hold no products, and until this round a
 * tile led to a listing saying «چیزی با این مشخصات پیدا نشد», which reads as a
 * broken shop rather than one still filling up.
 *
 * **What this feature is not is the point of most of these tests.** The first
 * attempt badged the front page's tiles with a gold band and greyed the
 * listing's marks, and was rejected twice in the same breath — «چرا رو کارتشون
 * تو صفحه هوم زدی؟؟؟» and «آیکونشون غیره فعال نشه، فقط هرجا کلیک میشه پاپاپ
 * کامینگ سون بیاد». So a closed section is drawn *identically* to an open one
 * and the whole of the feature is what happens on the click. That is one
 * invisible attribute and one script, which is exactly the kind of thing that
 * gets tidied away by somebody who cannot see what it does.
 */
class ComingSoonTest extends TestCase
{
    use RefreshDatabase;

    /** The four, and the four that are open, by slug. */
    private const SOON = ['boot', 'bag-set', 'accessory', 'sport-set'];

    private const OPEN = ['majlesi', 'sneaker', 'college', 'sandal'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
    }

    public function test_the_four_are_flagged_and_the_rest_are_not(): void
    {
        foreach (self::SOON as $slug) {
            $category = Category::where('slug', $slug)->first();

            $this->assertNotNull($category, "«{$slug}» is not in the catalogue at all.");
            $this->assertTrue($category->coming_soon, "«{$slug}» should be a coming-soon section.");
            $this->assertTrue($category->is_active, "«{$slug}» is announced, so it has to be on the shop.");
        }

        foreach (self::OPEN as $slug) {
            $this->assertFalse(
                Category::where('slug', $slug)->first()->coming_soon,
                "«{$slug}» sells things and must not be marked coming soon."
            );
        }
    }

    /**
     * The tiles under the hero, which is where the badge was tried and
     * rejected. Nothing on this row may say «به‌زودی» in any form a reader can
     * see; the attribute is all there is.
     */
    public function test_the_front_pages_tiles_carry_the_attribute_and_nothing_visible(): void
    {
        $row = $this->band($this->get('/')->assertOk()->getContent(), 'vp-category-row');

        foreach (self::SOON as $slug) {
            $name = Category::where('slug', $slug)->value('name');
            $this->assertStringContainsString('data-vp-soon="'.$name.'"', $row);
        }

        $this->assertSame(count(self::SOON), substr_count($row, 'data-vp-soon='));

        foreach (['به‌زودی', 'is-soon', 'vp-category-soon'] as $visible) {
            $this->assertStringNotContainsString(
                $visible,
                $row,
                'The front page tiles were badged once and the client said no. They stay as they are.'
            );
        }
    }

    public function test_the_phone_drawer_carries_the_attribute(): void
    {
        $page = $this->get('/')->assertOk()->getContent();
        $drawer = $this->band($page, 'vp-drawer-cats');

        foreach (self::SOON as $slug) {
            $category = Category::where('slug', $slug)->first();

            if (! $category->show_in_nav) {
                continue;
            }

            $this->assertStringContainsString('data-vp-soon="'.$category->name.'"', $drawer);
        }

        // One fewer than the four: «اکسسوری از منو حذف بشه» took that section
        // out of the drawer and out of nothing else, so it is still flagged and
        // still says «به‌زودی» on its tile, its strip mark and its own page.
        $this->assertSame(count(self::SOON) - 1, substr_count($drawer, 'data-vp-soon='));
        $this->assertStringNotContainsString('data-vp-soon="اکسسوری"', $drawer);
        $this->assertStringNotContainsString('به‌زودی', $drawer);
    }

    public function test_the_listings_category_strip_carries_the_attribute(): void
    {
        $strip = $this->band($this->get('/products')->assertOk()->getContent(), 'vp-shop-strip');

        foreach (self::SOON as $slug) {
            $name = Category::where('slug', $slug)->value('name');
            $this->assertStringContainsString('data-vp-soon="'.$name.'"', $strip);
        }

        $this->assertSame(count(self::SOON), substr_count($strip, 'data-vp-soon='));
        $this->assertStringNotContainsString('به‌زودی', $strip);
    }

    /**
     * The click is answered by a script, and a visitor without one still has
     * to be answered. The `href` is left alone on purpose, so the section's own
     * page is the fallback — and it says the same sentence, in full.
     */
    public function test_the_sections_own_page_says_it_and_shows_no_grid(): void
    {
        foreach (self::SOON as $slug) {
            $category = Category::where('slug', $slug)->first();

            $page = $this->get('/categories/'.$slug)->assertOk()->getContent();

            $this->assertStringContainsString('«'.$category->name.'» به‌زودی راه‌اندازی می‌شود.', $page);
            $this->assertStringNotContainsString('vp-shop-grid', $page);

            // The sort control and «۰ کالا» would be furniture contradicting
            // the sentence under them.
            $this->assertStringNotContainsString('vp-shop-bar', $page);
        }
    }

    public function test_an_open_section_still_lists_its_products(): void
    {
        $category = Category::where('slug', 'sneaker')->first();

        $page = $this->get('/categories/sneaker')->assertOk()->getContent();

        // The section's *name* in the sentence. The sentence itself is on every
        // page of the shop, inside the script that builds the card — asserting
        // on it alone would fail here for the wrong reason and would go on
        // failing after the feature was deleted.
        $this->assertStringNotContainsString('«'.$category->name.'» به‌زودی', $page);
        $this->assertStringContainsString('vp-shop-bar', $page);
    }

    /**
     * A section with nothing in it cannot narrow anything, so it is not a
     * filter. This is the one place a closed section is treated differently
     * from an open one, and it is not something a visitor can see.
     */
    public function test_a_closed_section_is_not_offered_as_a_filter(): void
    {
        $rail = $this->band($this->get('/products')->assertOk()->getContent(), 'vp-filter-list');

        foreach (self::SOON as $slug) {
            $this->assertStringNotContainsString('name="category" value="'.$slug.'"', $rail);
        }

        // And the one section that does have something in it still is one, so
        // this is not passing because the rail is empty.
        $this->assertStringContainsString('name="category" value="sneaker"', $rail);
    }

    /**
     * The card is built by a script that has to be on every page of the shop,
     * not only the one the tiles are on: the drawer is on all of them and the
     * listing's strip is on several.
     */
    public function test_the_card_can_be_built_on_every_page_of_the_shop(): void
    {
        foreach (['/', '/products', '/categories/sneaker', '/cart', '/about'] as $path) {
            $this->assertStringContainsString(
                'vp-soon-scrim',
                $this->get($path)->assertOk()->getContent(),
                "The «به‌زودی» card cannot be built on {$path}."
            );
        }
    }

    /**
     * The static preview keeps its own hand-written list of the eight sections
     * and its own copy of the drawer, and nothing else compares the two:
     * check-parity.js renders the home page, where a closed section is
     * indistinguishable from an open one by design, so it would report zero
     * with the two lists completely out of step.
     */
    public function test_the_static_preview_marks_the_same_four(): void
    {
        $preview = file_get_contents(base_path('../download-version/shoe-shop-rtl.html'));

        foreach (self::SOON as $slug) {
            $name = Category::where('slug', $slug)->value('name');
            $this->assertStringContainsString(
                'data-vp-soon="'.$name.'"',
                $preview,
                "The preview page does not know «{$name}» is closed. Re-run theme/make-rtl-page.js."
            );
        }

        // Twice each in the tile row and the drawer, less the one section the
        // drawer does not offer — «اکسسوری از منو حذف بشه».
        $this->assertSame(2 * count(self::SOON) - 1, substr_count($preview, 'data-vp-soon='));
    }

    /** The markup of one band of the page, by the class that opens it. */
    private function band(string $page, string $class): string
    {
        $open = strpos($page, $class);
        $this->assertNotFalse($open, "The page has no .{$class}.");

        // Far enough to cover the band and nothing like a second one: the
        // longest of these is the drawer's eight rows.
        return substr($page, $open, 4000);
    }
}
