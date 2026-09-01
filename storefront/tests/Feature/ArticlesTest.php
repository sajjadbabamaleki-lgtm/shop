<?php

namespace Tests\Feature;

use App\Models\Article;
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
