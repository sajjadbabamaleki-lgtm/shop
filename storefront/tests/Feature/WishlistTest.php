<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchOffer;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use App\Support\Branches\BranchOpener;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «لیست علاقمندی» — the saved products, and the story buttons that write to it.
 *
 * Two things this file exists to hold down.
 *
 * **The guard.** Saving is `auth:customer`, and a guest is sent to the
 * *shopper's* sign-in and not the staff one. That routing is decided by
 * `redirectGuestsTo` matching `*account*` on the route name, which means the
 * names in routes/web.php are load-bearing — rename them and the button starts
 * bouncing shoppers to a staff form their account cannot satisfy, with no test
 * failing anywhere near the rename.
 *
 * **The branch.** The list has no branch column on purpose: one person, one
 * list, across every franchise. What is per-branch is whether the saved shoe
 * can be shown and priced *here*, and that is the reading's job. So a shoe
 * saved at the main store is still saved when a franchise cannot show it, and
 * a product a franchise does not sell cannot be saved from that franchise at
 * all — the write goes through `purchasable()` the way the basket's does.
 *
 * Both of those branch tests earned their place immediately: the controller
 * first used `pricedHere()`, which reads like the branch scope and is not one —
 * it adds a `branch_price` column and filters nothing. The save test got a
 * redirect where it wanted a 404, and the list test got a 500, because
 * `shop.card` reads `$offer->price` on a shoe the franchise cannot price.
 */
class WishlistTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $tenant;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        $this->tenant = app(TenantContext::class);

        $this->customer = Customer::create([
            'name' => 'خریدار',
            'phone' => '09123456789',
            'password' => 'password-1234',
            'is_active' => true,
        ]);
    }

    /**
     * A second shop, opened the way every other test opens one.
     *
     * The seeders leave one branch, so a franchise has to be created rather
     * than looked up — `BranchOpener` is what gives it the central catalogue at
     * its own prices, which is the state these branch tests are about.
     */
    private function franchise(): Branch
    {
        return app(BranchOpener::class)->open(
            slug: 'shiraz', name: 'ویکی پلاس شیراز', markupPercent: 5, openingStock: 2,
        );
    }

    private function aProduct(): Product
    {
        return $this->tenant->forBranch(
            Branch::central(),
            fn () => Product::query()->purchasable()->pricedHere()->firstOrFail(),
        );
    }

    // --- who may save -----------------------------------------------------

    public function test_a_guest_is_sent_to_the_shoppers_sign_in_and_not_the_staff_one(): void
    {
        $product = $this->aProduct();

        $this->post('/account/wishlist', ['product' => $product->id])
            ->assertRedirect(route('account.enter'));

        $this->get('/account/wishlist')->assertRedirect(route('account.enter'));

        $this->assertDatabaseCount('wishlist_items', 0);
    }

    public function test_a_staff_session_cannot_save_to_a_shoppers_list(): void
    {
        $product = $this->aProduct();

        // Signed in on `web`, which is the staff guard. The shopper routes must
        // not accept it — §21, and the reason every staff route says
        // `auth:web` rather than a bare `auth`.
        $staff = User::factory()->create();

        $this->actingAs($staff, 'web')
            ->post('/account/wishlist', ['product' => $product->id])
            ->assertRedirect(route('account.enter'));

        $this->assertDatabaseCount('wishlist_items', 0);
    }

    // --- saving and unsaving ----------------------------------------------

    public function test_a_shopper_saves_a_product_and_it_appears_on_their_list(): void
    {
        $product = $this->aProduct();

        $this->actingAs($this->customer, 'customer')
            ->post('/account/wishlist', ['product' => $product->id])
            ->assertRedirect();

        $this->assertDatabaseHas('wishlist_items', [
            'customer_id' => $this->customer->id,
            'product_id' => $product->id,
        ]);

        $this->actingAs($this->customer, 'customer')
            ->get('/account/wishlist')
            ->assertOk()
            ->assertSee($product->title);
    }

    public function test_saving_twice_is_one_row(): void
    {
        $product = $this->aProduct();

        // The unique key is what makes this true, not a check in the
        // controller — a double tap on a phone is two requests in flight at
        // once, and both would pass a check-then-insert.
        $this->actingAs($this->customer, 'customer')->post('/account/wishlist', ['product' => $product->id]);
        $this->actingAs($this->customer, 'customer')->post('/account/wishlist', ['product' => $product->id]);

        $this->assertSame(1, WishlistItem::where('customer_id', $this->customer->id)->count());
    }

    public function test_a_shopper_takes_a_product_back_off_the_list(): void
    {
        $product = $this->aProduct();

        $this->actingAs($this->customer, 'customer')->post('/account/wishlist', ['product' => $product->id]);

        $this->actingAs($this->customer, 'customer')
            ->post('/account/wishlist/remove', ['product' => $product->id])
            ->assertRedirect();

        $this->assertDatabaseCount('wishlist_items', 0);
    }

    public function test_one_shopper_cannot_remove_anothers_saved_product(): void
    {
        $product = $this->aProduct();

        $other = Customer::create([
            'name' => 'دیگری', 'phone' => '09120000000',
            'password' => 'password-1234', 'is_active' => true,
        ]);

        $this->actingAs($this->customer, 'customer')->post('/account/wishlist', ['product' => $product->id]);

        $this->actingAs($other, 'customer')->post('/account/wishlist/remove', ['product' => $product->id]);

        $this->assertDatabaseHas('wishlist_items', [
            'customer_id' => $this->customer->id,
            'product_id' => $product->id,
        ]);
    }

    // --- the branch -------------------------------------------------------

    public function test_a_product_this_branch_does_not_sell_cannot_be_saved_from_it(): void
    {
        $product = $this->aProduct();

        $shiraz = $this->franchise();

        // Take the shoe off this franchise's shelf entirely.
        $variants = $product->variants()->pluck('id');
        $this->tenant->forBranch($shiraz, fn () => BranchOffer::whereIn('variant_id', $variants)->delete());

        $this->actingAs($this->customer, 'customer')
            ->post('/'.$shiraz->slug.'/account/wishlist', ['product' => $product->id])
            ->assertNotFound();

        $this->assertDatabaseCount('wishlist_items', 0);
    }

    public function test_a_saved_product_is_kept_even_where_a_branch_cannot_show_it(): void
    {
        $product = $this->aProduct();

        $this->actingAs($this->customer, 'customer')->post('/account/wishlist', ['product' => $product->id]);

        $shiraz = $this->franchise();
        $variants = $product->variants()->pluck('id');
        $this->tenant->forBranch($shiraz, fn () => BranchOffer::whereIn('variant_id', $variants)->delete());

        // Not on the franchise's page — it cannot price it — and still on the
        // row, and still on the main store's page. A list is the person's, not
        // the shop's.
        $this->actingAs($this->customer, 'customer')
            ->get('/'.$shiraz->slug.'/account/wishlist')
            ->assertOk()
            ->assertDontSee($product->title);

        $this->assertDatabaseHas('wishlist_items', [
            'customer_id' => $this->customer->id,
            'product_id' => $product->id,
        ]);

        $this->actingAs($this->customer, 'customer')
            ->get('/account/wishlist')
            ->assertOk()
            ->assertSee($product->title);
    }
}
