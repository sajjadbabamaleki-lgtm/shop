<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleComment;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * «مقالات» — «هیچ جایی برای مقالات در سایت نداریم», asked for as «متن ساده با
 * عکس و تیتر».
 *
 * Three things here are worth a test and the rest is a form:
 *
 * **A draft is a 404.** Route-model binding resolves on the slug alone, so
 * without the check in the controller an article somebody is still writing is
 * readable by anybody who is sent its address — and the panel's own link is
 * the likeliest way that address travels.
 *
 * **The body is printed as text, never as markup.** That is what was asked
 * for, and it is what keeps whatever gets pasted into the editor from taking
 * the page's shape.
 *
 * **The slug survives a Persian title.** `Str::slug()` transliterates, and on
 * Persian there is nothing to transliterate to: it returns an empty string,
 * and the second article would collide with the first on ''.
 */
class ArticlesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        /*
         * The shop opens with three articles, written by a migration —
         * `write_the_shops_first_three_articles`. Every case here is about a
         * catalogue of articles it controls, so it starts from none: a test
         * that says «an empty shop draws no band» has to be able to make the
         * shop empty, and one that counts what it wrote cannot count three
         * more it did not.
         *
         * Deleting rather than working around them is the honest version. The
         * migration is real content and is covered by `ArticleSeedTest`.
         */
        Article::query()->delete();
    }

    private function panel(): User
    {
        $user = User::create(['name' => 'مدیر', 'email' => 'panel@vikyplus.test', 'password' => 'secret']);
        $user->roles()->attach(Role::where('slug', Role::ADMIN)->sole());

        return $user;
    }

    private function write(array $fields = []): Article
    {
        return Article::create(array_merge([
            'slug' => Article::slugFor($fields['title'] ?? 'چطور سایز کفش را اندازه بگیریم'),
            'title' => 'چطور سایز کفش را اندازه بگیریم',
            'body' => 'یک کاغذ روی زمین بگذارید و پاشنه‌تان را به دیوار تکیه دهید.',
            'status' => Article::PUBLISHED,
            'published_at' => now()->subDay(),
        ], $fields));
    }

    // --- the shop's side --------------------------------------------------

    public function test_the_list_is_there_before_anything_is_written(): void
    {
        $this->get('/articles')
            ->assertOk()
            ->assertSee('مقالات', false)
            ->assertSee('هنوز مقاله‌ای منتشر نشده است.', false);
    }

    public function test_a_published_article_is_listed_and_readable(): void
    {
        $article = $this->write();

        $this->get('/articles')
            ->assertOk()
            ->assertSee('چطور سایز کفش را اندازه بگیریم', false)
            // `route()` and not the slug pasted into a path: a Persian slug is
            // percent-encoded in an href, so the raw letters never appear.
            ->assertSee(route('article', $article), false);

        $this->get('/articles/'.$article->slug)
            ->assertOk()
            ->assertSee('یک کاغذ روی زمین بگذارید', false);
    }

    /** A draft has an address and it is a 404, not a page with a badge on it. */
    public function test_a_draft_is_not_readable_and_not_listed(): void
    {
        $draft = $this->write(['status' => Article::DRAFT, 'published_at' => null]);

        $this->get('/articles')->assertOk()->assertDontSee('چطور سایز کفش', false);
        $this->get('/articles/'.$draft->slug)->assertNotFound();
    }

    /**
     * «منتشر شده» with tomorrow's date is scheduled, not live.
     *
     * The only thing a shop wants from a date field it can edit, and the one
     * case a status-only check would get wrong.
     */
    public function test_an_article_dated_ahead_is_not_published_yet(): void
    {
        $later = $this->write(['published_at' => now()->addWeek()]);

        $this->get('/articles')->assertOk()->assertDontSee('چطور سایز کفش', false);
        $this->get('/articles/'.$later->slug)->assertNotFound();
    }

    /**
     * The body is text. Whatever is typed into the editor reaches the page as
     * words, not as markup.
     */
    public function test_the_body_is_printed_as_text_and_never_as_markup(): void
    {
        $article = $this->write(['body' => 'یک <script>alert(1)</script> در متن.']);

        $this->get('/articles/'.$article->slug)
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    /** The article page offers more to read, and never itself. */
    public function test_the_article_page_offers_others_and_not_itself(): void
    {
        $one = $this->write();
        $two = $this->write(['title' => 'نگهداری از کفش چرم', 'slug' => 'نگهداری-از-کفش-چرم']);

        $this->get('/articles/'.$one->slug)
            ->assertOk()
            ->assertSee('نگهداری از کفش چرم', false)
            ->assertSee(route('article', $two), false);

        $this->assertSame([$two->id], $this->get('/articles/'.$one->slug)
            ->viewData('more')->pluck('id')->all());
    }

    /**
     * The footer promised this page, in both footers.
     *
     * A footer item pointing at '#' is the failure `ContentPagesTest` was
     * written for; this is the same check for the one link added since.
     */
    public function test_the_footer_links_to_it_rather_than_to_nowhere(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('مقالات', false)
            ->assertSee(route('articles'), false);
    }

    // --- the quote, the gallery and the tags ------------------------------

    /**
     * A pull-quote with nobody's name on it is a line the article is
     * emphasising; one with a name is somebody being quoted. The panel has to
     * be able to make either claim, and a name with no quote under it is
     * neither — it prints nothing, so it is not stored.
     */
    public function test_a_name_with_no_quote_under_it_is_not_stored(): void
    {
        $this->actingAs($this->panel())->post('/admin/articles', [
            'title' => 'بدون نقل‌قول',
            'body' => 'متن آزمایشی برای این مقاله.',
            'quote_by' => 'کسی که چیزی نگفته',
            'status' => Article::DRAFT,
        ])->assertRedirect();

        $article = Article::sole();
        $this->assertNull($article->quote);
        $this->assertNull($article->quote_by);
    }

    public function test_the_quote_and_its_speaker_reach_the_page(): void
    {
        $article = $this->write([
            'quote' => 'چرم زنده است و کار شما نگه داشتن آن در میانه است.',
            'quote_by' => 'کارگاه ویکی پلاس',
        ]);

        $this->get('/articles/'.$article->slug)
            ->assertOk()
            ->assertSee('چرم زنده است و کار شما نگه داشتن آن در میانه است.', false)
            ->assertSee('کارگاه ویکی پلاس', false);
    }

    /**
     * Tags are typed as one line and split on either comma.
     *
     * «کفش، چرم» is what a Persian keyboard produces, and a shop whose tags all
     * had a «،» stuck to them would be a bug nobody could see the cause of.
     */
    public function test_tags_split_on_the_persian_comma_as_well_as_the_latin_one(): void
    {
        $this->actingAs($this->panel())->post('/admin/articles', [
            'title' => 'برچسب‌دار',
            'body' => 'متن آزمایشی برای این مقاله.',
            'tags' => 'کفش چرم، نگهداری, زمستان',
            'status' => Article::DRAFT,
        ])->assertRedirect();

        $this->assertSame(['کفش چرم', 'نگهداری', 'زمستان'], Article::sole()->tagList());
    }

    /** Every chip leads somewhere: the listing, filtered to that tag. */
    public function test_a_tag_filters_the_listing(): void
    {
        $leather = $this->write(['title' => 'نگهداری از چرم', 'tags' => ['کفش چرم']]);
        $walking = $this->write(['title' => 'کفش پیاده‌روی', 'slug' => 'کفش-پیاده-روی', 'tags' => ['پیاده‌روی']]);

        $this->get('/articles?tag='.rawurlencode('کفش چرم'))
            ->assertOk()
            ->assertSee($leather->title, false)
            ->assertDontSee($walking->title, false);

        // And a way back out, or a filtered list is a dead end reached by
        // clicking.
        $this->get('/articles?tag='.rawurlencode('کفش چرم'))
            ->assertSee(route('articles'), false);
    }

    /**
     * «کفش» must not match «کفش چرم».
     *
     * A substring search on the json column would, which is why this uses
     * `whereJsonContains`.
     */
    public function test_a_tag_matches_whole_and_not_by_substring(): void
    {
        $this->write(['tags' => ['کفش چرم']]);

        $this->get('/articles?tag='.rawurlencode('کفش'))
            ->assertOk()
            ->assertSee('مقاله‌ای با این برچسب نیست.', false);
    }

    // --- what readers say -------------------------------------------------

    /**
     * The gate here is not the product page's, and it must not be.
     *
     * A shoe's comment is open to «فقط کسی که خریده», and that purchase is what
     * makes it worth reading; an article has no purchase behind it, so the rule
     * is a signed-in customer — and a guest is sent to the shopper's sign-in,
     * not the staff one.
     */
    public function test_a_guest_cannot_comment_and_is_sent_to_the_shoppers_sign_in(): void
    {
        $article = $this->write();

        $this->get('/articles/'.$article->slug)
            ->assertOk()
            ->assertSee('برای نوشتن نظر وارد حساب خود شوید.', false)
            ->assertDontSee('name="body"', false);

        $this->post('/articles/'.$article->slug.'/comments', ['body' => 'حرف من دربارهٔ این مقاله.'])
            ->assertRedirect(route('account.enter'));

        $this->assertSame(0, ArticleComment::count());
    }

    /** A signed-in customer writes, and it waits. */
    public function test_a_comment_waits_for_the_shop_before_it_is_printed(): void
    {
        $article = $this->write();
        $customer = Customer::create(['name' => 'بهاره', 'phone' => '09121112233', 'password' => 'password-1234']);

        $this->actingAs($customer, 'customer')
            ->post('/articles/'.$article->slug.'/comments', ['body' => 'واکس بی‌رنگ را امتحان کردم و فرق کرد.'])
            ->assertSessionHasNoErrors();

        $comment = ArticleComment::sole();
        $this->assertSame(ArticleComment::PENDING, $comment->status);
        $this->assertNull($comment->approved_at);

        $this->app['auth']->guard('customer')->logout();
        $this->get('/articles/'.$article->slug)
            ->assertOk()
            ->assertDontSee('واکس بی‌رنگ را امتحان کردم و فرق کرد.', false);
    }

    /** And the panel's queue publishes it, on the same screen as the shoes'. */
    public function test_the_panel_publishes_an_article_comment(): void
    {
        $article = $this->write();
        $customer = Customer::create(['name' => 'بهاره', 'phone' => '09121112233', 'password' => 'password-1234']);

        $comment = ArticleComment::create([
            'article_id' => $article->id,
            'customer_id' => $customer->id,
            'body' => 'این نظر منتظر بررسی است.',
        ]);

        $panel = $this->panel();

        $this->actingAs($panel)
            ->get('/admin/comments')
            ->assertOk()
            ->assertSee('نظرهای زیر مقاله‌ها', false)
            ->assertSee('این نظر منتظر بررسی است.', false);

        $this->actingAs($panel)
            ->post('/admin/comments/article/'.$comment->id, ['status' => ArticleComment::PUBLISHED])
            ->assertRedirect();

        $this->assertNotNull($comment->refresh()->approved_at);

        $this->app['auth']->guard('web')->logout();
        $this->get('/articles/'.$article->slug)
            ->assertOk()
            ->assertSee('این نظر منتظر بررسی است.', false);
    }

    /** A draft takes no comments, however its address is reached. */
    public function test_a_draft_takes_no_comments(): void
    {
        $draft = $this->write(['status' => Article::DRAFT, 'published_at' => null]);
        $customer = Customer::create(['name' => 'بهاره', 'phone' => '09121112233', 'password' => 'password-1234']);

        $this->actingAs($customer, 'customer')
            ->post('/articles/'.$draft->slug.'/comments', ['body' => 'حرف من دربارهٔ این مقاله.'])
            ->assertNotFound();

        $this->assertSame(0, ArticleComment::count());
    }

    // --- the panel's side -------------------------------------------------

    public function test_writing_one_needs_the_permission(): void
    {
        $this->get('/admin/articles')->assertRedirect(route('admin.login'));

        $staff = User::create(['name' => 'انباردار', 'email' => 'store@vikyplus.test', 'password' => 'secret']);
        $staff->roles()->attach(Role::where('slug', Role::MARKETPLACE_MANAGER)->sole());

        $this->actingAs($staff)->get('/admin/articles')->assertForbidden();
    }

    public function test_the_panel_writes_publishes_and_edits_one(): void
    {
        $panel = $this->panel();

        $this->actingAs($panel)->post('/admin/articles', [
            'title' => 'انتخاب پاشنه برای پای پهن',
            'slug' => '',
            'excerpt' => 'سه نکته کوتاه.',
            'body' => "خط اول.\nخط دوم.",
            'status' => Article::PUBLISHED,
        ])->assertRedirect();

        $article = Article::sole();
        $this->assertNotSame('', $article->slug);
        $this->assertNotNull($article->published_at);

        // Live on the shop, read signed out the way a visitor reads it.
        $this->app['auth']->guard('web')->logout();
        $this->get('/articles/'.$article->slug)->assertOk()->assertSee('خط دوم.', false);

        // Re-saving does not restamp the date, or every edit would jump the
        // article back to the top of the list.
        $was = $article->published_at;

        $this->actingAs($panel)->post('/admin/articles/'.$article->slug, [
            'title' => 'انتخاب پاشنه برای پای پهن',
            'slug' => $article->slug,
            'body' => 'متن تازه.',
            'status' => Article::PUBLISHED,
        ])->assertRedirect();

        $article->refresh();
        $this->assertSame('متن تازه.', $article->body);
        $this->assertTrue($was->equalTo($article->published_at));
    }

    /** Back to a draft, and the date goes with it — a date on a draft is a lie. */
    public function test_unpublishing_clears_the_date_and_takes_it_off_the_site(): void
    {
        $article = $this->write();
        $panel = $this->panel();

        $this->actingAs($panel)->post('/admin/articles/'.$article->slug, [
            'title' => $article->title,
            'slug' => $article->slug,
            'body' => $article->body,
            'status' => Article::DRAFT,
        ])->assertRedirect();

        $this->assertNull($article->refresh()->published_at);

        $this->app['auth']->guard('web')->logout();
        $this->get('/articles/'.$article->slug)->assertNotFound();
    }

    /**
     * A Persian title makes a usable, unique address.
     *
     * `Str::slug()` returns '' for a title with no Latin letters in it, so
     * without `Article::slugFor()` the second article collides with the first
     * on the empty string and the insert fails on the unique index.
     */
    public function test_two_persian_titles_do_not_collide_on_an_empty_slug(): void
    {
        $panel = $this->panel();

        foreach (['کفش پاییزی', 'کفش پاییزی'] as $title) {
            $this->actingAs($panel)->post('/admin/articles', [
                'title' => $title,
                'body' => 'متن آزمایشی برای این مقاله.',
                'status' => Article::DRAFT,
            ])->assertRedirect();
        }

        $slugs = Article::pluck('slug');

        $this->assertCount(2, $slugs);
        $this->assertCount(2, $slugs->unique());
        $this->assertNotContains('', $slugs);
    }

    /** «مقاله‌ها» is offered in the panel to somebody who may write them. */
    public function test_the_screen_is_in_the_panels_navigation(): void
    {
        $this->actingAs($this->panel())
            ->get('/admin')
            ->assertOk()
            ->assertSee(route('admin.articles'), false);
    }

    public function test_a_photograph_is_stored_and_shown(): void
    {
        Storage::fake('public');

        $this->actingAs($this->panel())->post('/admin/articles', [
            'title' => 'کفش برای پیاده‌روی طولانی',
            'body' => 'متن آزمایشی برای این مقاله.',
            'status' => Article::PUBLISHED,
            'image' => UploadedFile::fake()->image('shoe.jpg', 1200, 675),
        ])->assertRedirect();

        $article = Article::sole();

        $this->assertNotNull($article->image);
        Storage::disk('public')->assertExists(str_replace('storage/', '', $article->image));

        $this->app['auth']->guard('web')->logout();
        $this->get('/articles/'.$article->slug)->assertOk()->assertSee($article->image, false);
    }
}
