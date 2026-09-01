<?php

namespace Tests\Feature;

use App\Models\Article;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The three articles the shop opens with.
 *
 * «فعلا ۳ تا مقاله جدید واقعی با متن واقعی بزار» — written by
 * `write_the_shops_first_three_articles`, which is a migration and not a
 * seeder: `catalogue:seed` runs only on an empty catalogue and production has
 * not been empty for weeks, so a seeder would have shipped green and put
 * nothing on the live site.
 *
 * Every other article test empties the table in `setUp` so it can control what
 * is there. This is the one that says these three are real, published, and
 * carrying what they are supposed to carry — otherwise the migration could
 * quietly stop working and nothing would notice.
 */
class ArticleSeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
    }

    public function test_the_shop_opens_with_three_published_articles(): void
    {
        $this->assertSame(3, Article::published()->count());
    }

    /**
     * Each one is a real piece of writing, not a stub.
     *
     * A thousand characters is not a quality bar; it is the difference between
     * an article and a placeholder somebody meant to come back to. Every one of
     * these was over 1,300 when it was written.
     */
    public function test_each_one_carries_a_real_article_and_not_a_stub(): void
    {
        foreach (Article::published()->get() as $article) {
            $this->assertGreaterThan(
                1000,
                mb_strlen($article->body),
                "«{$article->title}» is too short to be an article."
            );

            $this->assertNotNull($article->excerpt, "«{$article->title}» has no excerpt.");
            $this->assertNotNull($article->image, "«{$article->title}» has no photograph.");
            $this->assertNotEmpty($article->tagList(), "«{$article->title}» has no tags.");
        }
    }

    /**
     * The paragraphs survive the heredoc.
     *
     * The bodies are indented in the migration so the code reads; the blank
     * lines between paragraphs are what `white-space: pre-line` draws on the
     * page. If the unindenting ever ate them the article would render as one
     * wall of text and every test above would still pass.
     */
    public function test_the_paragraphs_are_still_paragraphs(): void
    {
        foreach (Article::published()->get() as $article) {
            $this->assertStringContainsString("\n\n", $article->body);

            // And no line starts with the code's own indentation.
            $this->assertDoesNotMatchRegularExpression('/^[ \t]+\S/m', $article->body);
        }
    }

    /** They are readable, and the tags under them lead somewhere. */
    public function test_each_one_opens_and_its_tags_filter_the_listing(): void
    {
        foreach (Article::published()->get() as $article) {
            $this->get('/articles/'.$article->slug)
                ->assertOk()
                ->assertSee($article->title, false);

            foreach ($article->tagList() as $tag) {
                $this->get('/articles?tag='.rawurlencode($tag))
                    ->assertOk()
                    ->assertSee($article->title, false);
            }
        }
    }

    /** And all three are offered on the front page, which takes three. */
    public function test_all_three_are_on_the_front_page(): void
    {
        $shown = $this->get('/')->assertOk()->viewData('articles');

        $this->assertCount(3, $shown);
    }
}
