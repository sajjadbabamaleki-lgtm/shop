<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Support\Catalogue\CategoriseByName;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Filing a shoe by reading its name.
 *
 * **The names in here are the shop's real ones**, read off the live listing
 * rather than invented: 148 products under 57 distinct names, of which every
 * one opens with کتونی, ونس, نایک, کالج, صندل or اسلیپر. A test written against
 * made-up names would prove the regex and nothing about the catalogue.
 *
 * The seeded catalogue in this repository is five sneakers, so the migration
 * this covers does nothing locally and nothing in CI — which is exactly why it
 * needs a test of its own. Without one, the only place it is ever exercised is
 * the live site.
 */
class CategoriseByNameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
        app(TenantContext::class)->set(Branch::central());
    }

    private function product(string $title): Product
    {
        static $n = 0;
        $n++;

        return Product::create([
            'slug' => 'p-'.$n,
            'title' => $title,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);
    }

    /** @return list<string> */
    private function sectionsOf(Product $product): array
    {
        return $product->categories()->pluck('slug')->sort()->values()->all();
    }

    // --- the rule, on the shop's own names ---------------------------------

    public static function realNames(): array
    {
        return [
            // Sneakers, under all three of their openings.
            ['کتونی نایک وی تو کی رنگ موکا', ['sneaker']],
            ['کتونی نایک جردن وان ساق کوتاه Air Jordan 1 Low رنگ سفید', ['sneaker']],
            ['کتونی نیوبالانس New balance 530 رنگ نسکافه ای', ['sneaker']],
            ['کتونی گلدن گوس رنگ صورتی Golden Goose', ['sneaker']],
            ['کتونی آن رانینگ ON Running رنگ طوسی', ['sneaker']],
            ['ونس کانورس ال استار ساق بلند Converse All Star High رنگ لجنی', ['sneaker']],
            ['ونس آدیداس سامبا Adidas Samba رنگ کرم قهوه ای', ['sneaker']],
            ['ونس پولو Polo Vans رنگ قرمز', ['sneaker']],
            ['ونس زارا Zara Vans رنگ سفید', ['sneaker']],
            ['نایک جردن تراویس اسکات رنگ سفید کرم Nike jordan travis scott', ['sneaker']],

            // The closed flats.
            ['کالج بافتی رنگ نسکافه ای', ['college']],
            ['کالج قفل و دایره رنگ موکا', ['college']],

            // The open ones.
            ['صندل حبابی رنگ کرم', ['sandal']],
            ['صندل توری میو رنگ مشکی', ['sandal']],
            ['صندل ادیداس سامبا چسبی Adidas Samba Sandal رنگ صورتی', ['sandal']],
            ['صندل انگشتی کمربندی رنگ مشکی', ['sandal']],
            ['اسلیپر حصیری رنگ طلایی', ['sandal']],

            // And the two families that are an occasion as well as a sandal.
            ['صندل عروسی توری کمربندی رنگ عسلی', ['majlesi', 'sandal']],
            ['صندل عروسکی توری کمربندی رنگ کرم', ['majlesi', 'sandal']],
            ['صندل 2 سگگ نگینی مشکی', ['majlesi', 'sandal']],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('realNames')]
    public function test_a_real_product_name_lands_in_the_right_sections(string $title, array $expected): void
    {
        $got = CategoriseByName::sectionsFor($title);
        sort($got);
        sort($expected);

        $this->assertSame($expected, $got, "«{$title}» was filed wrong.");
    }

    /** A name the rule has never seen is left alone rather than guessed at. */
    public function test_a_name_the_rule_does_not_know_gets_no_section(): void
    {
        $this->assertSame([], CategoriseByName::sectionsFor('چکمه جیر رنگ مشکی'));
        $this->assertSame([], CategoriseByName::sectionsFor(''));
    }

    // --- what the run does -------------------------------------------------

    public function test_it_files_the_products_and_reports_what_it_did(): void
    {
        $college = $this->product('کالج بافتی رنگ مشکی');
        $sandal = $this->product('صندل حبابی رنگ سفید');
        $both = $this->product('صندل عروسی توری کمربندی رنگ قهوه ای');
        $stranger = $this->product('چکمه جیر رنگ مشکی');

        $result = CategoriseByName::run();

        $this->assertSame(['college'], $this->sectionsOf($college));
        $this->assertSame(['sandal'], $this->sectionsOf($sandal));
        $this->assertSame(['majlesi', 'sandal'], $this->sectionsOf($both));
        $this->assertSame([], $this->sectionsOf($stranger));

        $this->assertSame(3, $result['filed']);
        $this->assertContains('چکمه جیر رنگ مشکی', $result['unknown']);
    }

    /** `--dry-run` reports the same plan and writes none of it. */
    public function test_a_dry_run_changes_nothing(): void
    {
        $college = $this->product('کالج قفل و دایره رنگ مشکی');

        $result = CategoriseByName::run(dryRun: true);

        $this->assertSame(1, $result['filed']);
        $this->assertSame([], $this->sectionsOf($college));
    }

    /**
     * **It adds; it never takes away.** Somebody who has filed a shoe by hand
     * in `/admin/catalogue` has made a decision, and a rule that reads names
     * does not get to overrule it.
     */
    public function test_it_never_removes_a_section_somebody_chose(): void
    {
        $shoe = $this->product('کتونی نایک وومرو Nike Vomero 5 رنگ مشکی');
        $shoe->categories()->attach(Category::where('slug', 'majlesi')->firstOrFail()->id);

        CategoriseByName::run();

        $this->assertSame(['majlesi', 'sneaker'], $this->sectionsOf($shoe));
    }

    /** Running it twice files nothing the second time. */
    public function test_running_it_again_is_a_no_op(): void
    {
        $this->product('صندل توری میو رنگ قهوه ای');

        CategoriseByName::run();
        $second = CategoriseByName::run();

        $this->assertSame(0, $second['filed']);
    }

    /**
     * The section is no longer empty — which is the whole complaint.
     *
     * Asserted on the category's own products rather than on the rendered
     * listing, because the listing is `listable()`: published, and carrying an
     * offer at this branch. Whether a given shoe passes that is a question
     * about price and stock, and other tests own it. What this change is
     * responsible for is the shoe being in the section at all.
     */
    public function test_the_section_is_no_longer_empty(): void
    {
        $shoe = $this->product('کالج بافتی رنگ سرمه ای');

        $college = Category::where('slug', 'college')->firstOrFail();
        $this->assertSame(0, $college->products()->count(), 'The section already had something in it.');

        CategoriseByName::run();

        $this->assertSame([$shoe->id], $college->products()->pluck('products.id')->all());
    }

    public function test_the_command_runs_and_names_what_it_could_not_place(): void
    {
        $this->product('صندل انگشتی کمربندی رنگ کرم');
        $this->product('چکمه جیر رنگ مشکی');

        $this->artisan('catalogue:categorise')
            ->expectsOutputToContain('چکمه جیر رنگ مشکی')
            ->assertExitCode(0);
    }
}
