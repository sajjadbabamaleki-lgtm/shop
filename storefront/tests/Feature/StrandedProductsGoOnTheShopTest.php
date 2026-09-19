<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shoes the panel made and never put on the shop.
 *
 * The form is fixed, which fixes the next product and not the last one: every
 * shoe added through the panel before today was saved with `published_at`
 * null, and sat in the catalogue marked «فعال» on no page of the shop. «اره
 * بکن.»
 *
 * What this file is really for is the *fingerprint*. Publishing everything
 * unpublished would put back the five setup shoes, the payment-test product
 * and any staged import — three separate decisions somebody else made on
 * purpose — so each of them has a test here saying it stayed where it was.
 */
class StrandedProductsGoOnTheShopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);
    }

    private function migration(): object
    {
        return require database_path(
            'migrations/2026_09_19_150000_put_the_unpublished_panel_products_on_the_shop.php',
        );
    }

    /** A product made in the panel: active, and never published. */
    private function strandedInThePanel(string $title): Product
    {
        return Product::create([
            'slug' => 'stranded-'.mb_substr(md5($title), 0, 8),
            'title' => $title,
            'status' => 'active',
            'published_at' => null,
        ]);
    }

    public function test_it_publishes_a_shoe_the_panel_left_behind(): void
    {
        $shoe = $this->strandedInThePanel('کتونی زنانه نایک مدل Air Force 1 سفید');

        $this->assertNull($shoe->published_at);

        $this->migration()->up();

        $published = $shoe->fresh()->published_at;

        $this->assertNotNull($published, 'The shoe is still off the shop.');
        $this->assertTrue($published->isPast() || $published->isToday());
    }

    /** More than one, because the client photographed two. */
    public function test_it_takes_every_one_of_them(): void
    {
        $shoes = collect(['کتونی زنانه نایک مدل Air Force 1 سفید', 'کفش'])
            ->map(fn (string $title) => $this->strandedInThePanel($title));

        $this->migration()->up();

        foreach ($shoes as $shoe) {
            $this->assertNotNull($shoe->fresh()->published_at, "«{$shoe->title}» is still off the shop.");
        }
    }

    /**
     * **A staged import stays staged.** `basalam:import --publish=false`
     * writes `draft`, and its own comment says a re-run must not put back a
     * product a shopkeeper archived. Neither may this.
     */
    public function test_a_staged_import_is_left_alone(): void
    {
        $draft = Product::create([
            'slug' => 'imported-draft',
            'title' => 'کتونی وارداتی، هنوز منتشر نشده',
            'status' => 'draft',
            'published_at' => null,
            'source' => 'basalam',
            'source_id' => '999999',
        ]);

        $this->migration()->up();

        $this->assertNull($draft->fresh()->published_at, 'A staged import was put on the shop.');
    }

    /**
     * **A retired shoe stays retired.** The five setup shoes and the
     * payment-test product were archived with their dates cleared, at the
     * shop's own instruction, and this must not undo that.
     */
    public function test_a_retired_shoe_is_not_resurrected(): void
    {
        $product = Product::where('slug', 'golden-goose')->firstOrFail();
        $product->forceFill(['status' => 'archived', 'published_at' => null])->save();

        $this->migration()->up();

        $this->assertNull($product->fresh()->published_at, 'A retired shoe was put back on the shop.');
        $this->assertSame('archived', $product->fresh()->status);
    }

    /** A shoe already on the shop keeps the date it went up. */
    public function test_it_does_not_restamp_a_shoe_that_is_already_up(): void
    {
        $product = Product::where('slug', 'new-balance-530')->firstOrFail();
        $was = $product->published_at;

        $this->assertNotNull($was);

        $this->migration()->up();

        $this->assertEquals($was->timestamp, $product->fresh()->published_at->timestamp);
    }

    /** And the published shoe really is on the shop, not merely dated. */
    public function test_the_shoe_reaches_the_listing(): void
    {
        $shoe = $this->strandedInThePanel('کتونی زنانه نایک مدل Air Force 1 سفید');

        $this->migration()->up();

        $listable = app(TenantContext::class)->forBranch(
            Branch::central(),
            fn () => Product::query()->where('status', 'active')
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->whereKey($shoe->id)->exists(),
        );

        $this->assertTrue($listable, 'The date was set but the shop still cannot reach it.');
    }

    /**
     * It never throws, whatever it finds — `migrate --force` runs under
     * `set -eu` at boot, and a migration that throws stops the shop starting.
     */
    public function test_it_survives_a_shop_with_nothing_in_it(): void
    {
        Product::query()->delete();

        $this->migration()->up();

        $this->assertTrue(true, 'It returned rather than throwing, which is the assertion.');
    }
}
