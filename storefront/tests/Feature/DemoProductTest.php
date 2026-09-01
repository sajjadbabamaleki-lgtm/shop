<?php

namespace Tests\Feature;

use App\Console\Commands\MakeDemoProduct;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Variant;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The cheap, buyable item that exists so a card can be put through the gateway.
 *
 * «یه کالای ۱۰۰ هزارتومنی بزار تو فروشگاه تست کنم درگاه پرداختو» — and the
 * catalogue could not answer that on its own, because the cheapest shoe in it
 * costs millions and nobody wants to run a real card through that twice.
 *
 * What is worth holding is that it is a *real* product all the way down — a
 * variant, a branch offer, stock, and a movement behind the stock — because a
 * shortcut on any of those would exercise a path the shop does not have, and
 * the test would prove nothing about the real checkout.
 */
class DemoProductTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BranchSeeder::class, CatalogueSeeder::class]);

        $this->branch = Branch::central();
    }

    private function variant(): ?Variant
    {
        return app(TenantContext::class)->forBranch(
            $this->branch,
            fn () => Variant::withoutGlobalScopes()->where('sku', 'VP-TEST-ITEM')->first(),
        );
    }

    /** Priced in Toman on the command line, stored in Rial like everything else. */
    public function test_it_makes_a_product_a_customer_could_really_buy(): void
    {
        $this->artisan('demo:product --toman=100000')->assertSuccessful();

        $variant = $this->variant();

        $this->assertNotNull($variant);

        app(TenantContext::class)->forBranch($this->branch, function () use ($variant) {
            $variant = $variant->fresh();

            // Ten Rial to the Toman, the same conversion `toman()` prints with.
            // Backwards here is a price ten times wrong that looks normal on
            // both sides.
            $this->assertSame(1_000_000, $variant->offer->price);
            $this->assertGreaterThan(0, $variant->sellableStock());
            $this->assertTrue($variant->isSellable(), 'The test item cannot actually be bought.');
        });

        // And it is reachable, which is the whole point.
        $this->get('/products/'.MakeDemoProduct::SLUG)->assertOk()->assertSee('۱۰۰٬۰۰۰');
    }

    /** Run twice by mistake and it is still one product, not two. */
    public function test_it_can_be_run_again_without_making_a_second_one(): void
    {
        $this->artisan('demo:product')->assertSuccessful();
        $this->artisan('demo:product --toman=50000')->assertSuccessful();

        app(TenantContext::class)->forBranch($this->branch, function () {
            $this->assertSame(1, Variant::withoutGlobalScopes()->where('sku', 'VP-TEST-ITEM')->count());
            $this->assertSame(1, Product::where('slug', MakeDemoProduct::SLUG)->count());

            // And the second run's price is the one that stuck.
            $this->assertSame(500_000, $this->variant()->fresh()->offer->price);
        });
    }

    /**
     * **`--remove` takes it off the shop and leaves the record alone.**
     *
     * It retires rather than deletes: an order that bought the thing keeps its
     * line, which is what «حذف» has to mean for anything sellable. What must be
     * true afterwards is that no customer can reach it.
     */
    public function test_removing_it_takes_it_off_the_shop_without_deleting_the_record(): void
    {
        $this->artisan('demo:product')->assertSuccessful();
        $this->get('/products/'.MakeDemoProduct::SLUG)->assertOk();

        $this->artisan('demo:product --remove')->assertSuccessful();

        $this->get('/products/'.MakeDemoProduct::SLUG)->assertNotFound();

        app(TenantContext::class)->forBranch($this->branch, function () {
            $variant = $this->variant();

            $this->assertNotNull($variant, 'The row was deleted, so any order that bought it lost its line.');
            $this->assertFalse($variant->fresh()->isSellable());
        });
    }

    /**
     * The migration that does the same thing on the live shop.
     *
     * «کالای آزمایشی که قبلا تو سایت گذاشته بودیم هنوز هست باید حذف بشه» — and
     * nobody runs a command on the live site: the deploy runs
     * `php artisan migrate --force` and nothing else. So
     * `take_the_test_product_off_the_live_shop` is `remove()` written again as
     * a migration, and **two copies of one rule is exactly the thing that
     * drifts**. This asserts the migration reaches the same state the command
     * does, against a shop the command has just stocked.
     */
    public function test_the_migration_takes_it_off_the_shop_the_same_way(): void
    {
        $this->artisan('demo:product')->assertSuccessful();
        $this->get('/products/'.MakeDemoProduct::SLUG)->assertOk();

        $this->migration()->up();

        $this->get('/products/'.MakeDemoProduct::SLUG)->assertNotFound();

        app(TenantContext::class)->forBranch($this->branch, function () {
            $variant = $this->variant();

            $this->assertNotNull($variant, 'The row was deleted, so any order that bought it lost its line.');
            $this->assertFalse($variant->fresh()->isSellable());
        });
    }

    /** And it is safe on a shop that never had one — production may not. */
    public function test_the_migration_does_nothing_when_there_is_no_test_product(): void
    {
        $this->assertNull(Product::where('slug', MakeDemoProduct::SLUG)->first());

        $this->migration()->up();

        $this->assertNull(Product::where('slug', MakeDemoProduct::SLUG)->first());
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_08_31_170000_take_the_test_product_off_the_live_shop.php');
    }

    /** And it can be put back, which is how a second test run happens. */
    public function test_it_comes_back_after_being_removed(): void
    {
        $this->artisan('demo:product')->assertSuccessful();
        $this->artisan('demo:product --remove')->assertSuccessful();
        $this->artisan('demo:product')->assertSuccessful();

        $this->get('/products/'.MakeDemoProduct::SLUG)->assertOk();
    }

    /**
     * On `at-the-door` there is no gateway to reach, so somebody would buy the
     * item, see no bank page and conclude the gateway was broken. The command
     * says which driver is live before that happens.
     */
    public function test_it_says_when_there_is_no_gateway_to_test_against(): void
    {
        config(['services.payment.driver' => 'at-the-door']);

        $this->artisan('demo:product')
            ->expectsOutputToContain('PAYMENT_DRIVER=zarinpal')
            ->assertSuccessful();
    }

    /** And it says so when the gateway is pretending. */
    public function test_it_warns_when_the_gateway_is_in_sandbox(): void
    {
        config(['services.payment.driver' => 'zarinpal', 'services.payment.zarinpal.sandbox' => true]);

        $this->artisan('demo:product')
            ->expectsOutputToContain('ZARINPAL_SANDBOX')
            ->assertSuccessful();
    }
}
