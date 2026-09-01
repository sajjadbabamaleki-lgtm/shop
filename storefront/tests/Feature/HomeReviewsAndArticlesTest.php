<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductComment;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two bands the client put on the front page.
 *
 * «نظرات باید قبل از برندها باشه» and «مقالات آخرین بخش قبل از سوالات متداول
 * باشه» — so the order is part of the specification and is asserted as such.
 *
 * **What this really guards is that neither band invents anything.** Both draw
 * live rows, both are absent when there are none, and a front page carrying a
 * testimonial nobody wrote is the one lie a shop must not tell. That is also
 * why neither exists in `download-version/`, which is published — and why
 * `check-parity.js` hides both before it shoots.
 */
class HomeReviewsAndArticlesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
        app(TenantContext::class)->set(Branch::central());

        // The shop opens with three articles, written by a migration. These
        // cases are about a front page whose bands they control — including
        // the one that says an empty shop draws neither — so they start from
        // none. `ArticleSeedTest` is what covers the migration's own three.
        Article::query()->delete();
    }

    private function review(int $rating = 5, string $body = 'کفش راحتی است و سایزش درست بود.', ?string $status = null): ProductComment
    {
        static $n = 0;
        $n++;

        return ProductComment::create([
            'product_id' => Product::firstOrFail()->id,
            'customer_id' => Customer::create([
                'name' => 'مشتری '.$n,
                'phone' => '0912000'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
                'password' => 'password-1234',
            ])->id,
            'body' => $body,
            'rating' => $rating,
            'status' => $status ?? ProductComment::PUBLISHED,
            'approved_at' => $status === null ? now() : null,
        ]);
    }

    private function article(array $fields = []): Article
    {
        $title = $fields['title'] ?? 'نگهداری از کفش چرم';

        return Article::create(array_merge([
            'slug' => Article::slugFor($title),
            'title' => $title,
            'body' => 'متن مقاله.',
            'status' => Article::PUBLISHED,
            'published_at' => now()->subDay(),
        ], $fields));
    }

    // --- absent until there is something to show --------------------------

    /**
     * An empty shop draws neither band.
     *
     * Not a placeholder, not a sample, not «به‌زودی». The front page is where a
     * visitor decides whether to believe the shop, and a row of invented
     * reviews there is worse than no row.
     */
    public function test_neither_band_is_drawn_on_a_shop_with_nothing_in_them(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('نظر مشتریان', false)
            ->assertDontSee('vp-home-arts', false);
    }

    public function test_an_unapproved_review_does_not_put_the_band_on_the_front_page(): void
    {
        $this->review(5, 'این نظر هنوز تأیید نشده است.', ProductComment::PENDING);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('نظر مشتریان', false)
            ->assertDontSee('این نظر هنوز تأیید نشده است.', false);
    }

    public function test_a_draft_article_does_not_put_the_band_on_the_front_page(): void
    {
        $this->article(['status' => Article::DRAFT, 'published_at' => null]);

        $this->get('/')->assertOk()->assertDontSee('vp-home-arts', false);
    }

    /**
     * A review with no score stays off this band.
     *
     * The card is built around five stars, and one with none in a row of cards
     * that have them reads as nought out of five. It is still on the shoe's own
     * page; it is simply not what this band is for.
     */
    public function test_a_review_with_no_score_stays_off_the_front_page(): void
    {
        $this->review()->update(['rating' => null]);

        $this->get('/')->assertOk()->assertDontSee('نظر مشتریان', false);
    }

    // --- what they show ---------------------------------------------------

    public function test_the_reviews_band_shows_an_approved_review(): void
    {
        $this->review(4, 'یک ماه است می‌پوشمش و هنوز مثل روز اول است.');

        $this->get('/')
            ->assertOk()
            ->assertSee('نظر مشتریان', false)
            ->assertSee('یک ماه است می‌پوشمش و هنوز مثل روز اول است.', false)
            // The shoe it is about, which is where the card's foot links.
            ->assertSee('خریدار', false);
    }

    public function test_the_articles_band_shows_three_at_most_newest_first(): void
    {
        foreach (['یک', 'دو', 'سه', 'چهار'] as $i => $word) {
            $this->article(['title' => 'مقالهٔ '.$word, 'published_at' => now()->subDays(10 - $i)]);
        }

        $shown = $this->get('/')->assertOk()->viewData('articles');

        $this->assertCount(3, $shown);
        $this->assertSame(['مقالهٔ چهار', 'مقالهٔ سه', 'مقالهٔ دو'], $shown->pluck('title')->all());
    }

    // --- where they sit ---------------------------------------------------

    /**
     * The order the client asked for, read off the rendered page.
     *
     * «نظرات باید قبل از برندها باشه» and «مقالات آخرین بخش قبل از سوالات
     * متداول باشه». Asserted by position rather than by eye, because a band
     * that lands in the wrong place still renders perfectly and nothing else
     * here would notice.
     */
    public function test_the_two_bands_sit_where_the_client_put_them(): void
    {
        $this->review();
        $this->article();

        $page = $this->get('/')->assertOk()->getContent();

        $reviews = strpos($page, 'vp-home-reviews');
        $brands = strpos($page, 'vp-brands-section');
        $articles = strpos($page, 'vp-home-arts');
        $faq = strpos($page, 'vp-home-faq');

        $this->assertNotFalse($reviews, 'The reviews band is not on the page at all.');
        $this->assertNotFalse($articles, 'The articles band is not on the page at all.');

        $this->assertLessThan($brands, $reviews, '«نظر مشتریان» must come before the brand strip.');
        $this->assertGreaterThan($brands, $articles, '«مقالات» must come after the brand strip.');
        $this->assertLessThan($faq, $articles, '«مقالات» must be the last band before the FAQ.');
    }

    /**
     * Neither band exists in the published static preview.
     *
     * That directory goes up to a public address. A copy of this page carrying
     * testimonials nobody wrote, or articles nobody published, would be a
     * fabrication rather than a design preview — so the preview has neither,
     * and `check-parity.js` hides both on the Laravel side before it shoots.
     */
    public function test_the_published_preview_carries_neither_band(): void
    {
        $preview = file_get_contents(base_path('../download-version/shoe-shop-rtl.html'));

        $this->assertStringNotContainsString('vp-home-reviews', $preview);
        $this->assertStringNotContainsString('vp-home-arts', $preview);

        // And the checker knows to hide them, or the first approved review
        // would break every parity run from then on.
        $checker = file_get_contents(base_path('../theme/check-parity.js'));
        $this->assertStringContainsString('.vp-home-reviews, .vp-home-arts', $checker);
    }
}
