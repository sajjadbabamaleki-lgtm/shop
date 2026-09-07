<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Product;
use App\Support\Catalogue\BrandByName;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reading a shoe's brand off its own name.
 *
 * The shop names a product for what it is, then for its make, then for its
 * colour, and usually carries the Latin name beside the Persian one. Nothing
 * fills `brand_id`: the import writes none and the panel's برند select is
 * optional — which stayed invisible until «برندهای موجود» began counting.
 *
 * The two rules with teeth are the order and «آن».
 */
class BrandByNameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);

        app(TenantContext::class)->set(Branch::central());
    }

    /**
     * **Jordan before Nike.** Air Jordan is Nike's and the shop writes it that
     * way, so both words are in the name and only one of them is the tile the
     * shoe belongs on.
     */
    public function test_a_jordan_is_a_jordan_and_not_a_nike(): void
    {
        $this->assertSame('jordan', BrandByName::brandFor('نایک جردن تراویس اسکات رنگ یشمی', 'Nike jordan travis scott'));
        $this->assertSame('jordan', BrandByName::brandFor('کتونی نایک جردن وان ساق بلند Air Jordan 1 High رنگ قرمز'));
        $this->assertSame('nike', BrandByName::brandFor('کتونی نایک وی تو کی رنگ موکا'));
    }

    /**
     * **«آن» alone is never On.** It is the ordinary Persian word for «that»,
     * and a brand that matched a pronoun would file half a catalogue under it
     * with nothing looking wrong until the strip printed the number.
     */
    public function test_on_is_read_from_the_running_and_never_from_the_pronoun(): void
    {
        $this->assertSame('on', BrandByName::brandFor('کتونی آن رانینگ ON Running رنگ مشکی'));
        $this->assertSame('on', BrandByName::brandFor('کتونی آن کلادتیلت'));

        $this->assertNull(BrandByName::brandFor('کیف دستی آن روزها رنگ کرم'));
        $this->assertNull(BrandByName::brandFor('صندل حبابی رنگ کرم'));
    }

    /** Both keyboards, one answer — the same folding search uses. */
    public function test_it_reads_a_name_typed_on_another_keyboard(): void
    {
        // Arabic ك and ي, where a Persian keyboard types ک and ی.
        $this->assertSame('nike', BrandByName::brandFor('كتوني نايك ايرمكس'));
        $this->assertSame('new-balance', BrandByName::brandFor('کتونی نیو بالانس ۵۳۰ رنگ سفید', 'New Balance 530'));
        $this->assertSame('golden-goose', BrandByName::brandFor('کتونی گلدن گوس رنگ مشکی', 'Golden Goose'));
    }

    /**
     * It fills blanks and overrules nobody: a brand chosen in the panel is a
     * decision, and this is a convenience.
     */
    public function test_it_only_ever_fills_a_blank(): void
    {
        $jordan = Brand::where('slug', 'jordan')->firstOrFail();
        $nike = Brand::where('slug', 'nike')->firstOrFail();

        $mislabelled = Product::create([
            'slug' => 'a-nike-somebody-filed-as-jordan',
            'title' => 'کتونی نایک ایر مکس رنگ مشکی',
            'short_title' => 'نایک ایر مکس',
            'brand_id' => $jordan->id,
            'status' => 'active',
            'published_at' => now(),
        ]);

        $blank = Product::create([
            'slug' => 'a-nike-with-no-brand',
            'title' => 'کتونی نایک وی تو کی رنگ سفید',
            'short_title' => 'نایک وی تو کی',
            'status' => 'active',
            'published_at' => now(),
        ]);

        $result = BrandByName::run();

        $this->assertSame($jordan->id, $mislabelled->fresh()->brand_id, 'somebody’s own choice was overruled');
        $this->assertSame($nike->id, $blank->fresh()->brand_id);
        $this->assertSame(1, $result['set']);
    }

    /** A dry run reads out the same plan and writes nothing. */
    public function test_a_dry_run_writes_nothing(): void
    {
        Product::create([
            'slug' => 'another-nike',
            'title' => 'کتونی نایک زوم رنگ آبی',
            'short_title' => 'نایک زوم',
            'status' => 'active',
            'published_at' => now(),
        ]);

        $plan = BrandByName::run(dryRun: true);

        $this->assertSame(1, $plan['set']);
        $this->assertNull(Product::where('slug', 'another-nike')->value('brand_id'));

        $this->artisan('catalogue:brand --dry-run')->assertSuccessful();
        $this->assertNull(Product::where('slug', 'another-nike')->value('brand_id'));

        $this->artisan('catalogue:brand')->assertSuccessful();
        $this->assertNotNull(Product::where('slug', 'another-nike')->value('brand_id'));
    }

    /**
     * The names it does not know are the report: every one is a shoe that will
     * never be counted on the strip, and the command prints them so somebody
     * can choose in the panel.
     */
    public function test_it_names_what_it_could_not_read(): void
    {
        Product::create([
            'slug' => 'a-sandal',
            'title' => 'صندل عروسی رنگ کرم',
            'short_title' => 'صندل عروسی',
            'status' => 'active',
            'published_at' => now(),
        ]);

        $this->assertContains('صندل عروسی رنگ کرم', BrandByName::run(dryRun: true)['unknown']);
    }
}
