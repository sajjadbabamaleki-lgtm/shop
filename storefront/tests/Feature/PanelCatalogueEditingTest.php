<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchOffer;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Variant;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two things the shop could not do from `/admin/catalogue`.
 *
 * «چرا نمیشه از پنل ادمین قیمت های قبلیرو ادیت کرد؟؟؟؟» and «چرا وقتی یه محصول
 * جدید از پنل ادمین اضافه میشه میزنه منتشر نشده؟؟؟؟» — both reported with a
 * photograph of the catalogue list showing a new shoe marked «فعال» and
 * «منتشر نشده».
 *
 * Both were real, and neither could be seen from here before: the suite had
 * tests that a product can be *created* and that a price can be changed on
 * `/admin/pricing`, and no test asked whether the screen somebody is actually
 * standing on can do either.
 */
class PanelCatalogueEditingTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        // The same shape `CatalogueAndStaffTest` uses: the branch comes from
        // the signed-in user, and the role is what the permission middleware
        // on these routes asks about.
        app(TenantContext::class)->set(Branch::central());

        $this->staff = User::factory()->create();
        $this->staff->roles()->attach(Role::where('slug', Role::ADMIN)->firstOrFail());
        $this->staff = $this->staff->fresh();
    }

    private function aSize(): Variant
    {
        return Product::where('slug', 'new-balance-530')->firstOrFail()
            ->variants()->withoutGlobalScopes()->firstOrFail();
    }

    private function offerFor(Variant $variant): BranchOffer
    {
        return BranchOffer::withoutGlobalScopes()
            ->where('branch_id', Branch::central()->id)
            ->where('variant_id', $variant->id)
            ->firstOrFail();
    }

    /**
     * **A price can be changed from the shoe's own screen.**
     *
     * It could always be changed at `/admin/pricing`; it could not be changed
     * from the page somebody repricing this shoe is standing on, which from
     * where the shop was standing is the same as «you cannot».
     */
    public function test_a_price_can_be_edited_from_the_product_screen(): void
    {
        $variant = $this->aSize();

        $this->actingAs($this->staff)
            ->post(route('admin.product.variants.price', [$variant->product, $variant]), [
                'price' => '۴,۲۰۰,۰۰۰',
                'compare_at_price' => '۵۰۰۰۰۰۰',
            ])
            ->assertRedirect(route('admin.product.edit', $variant->product));

        $offer = $this->offerFor($variant);

        // Typed in Toman, stored in Rial, and the separators and the Persian
        // digits are punctuation rather than part of the number.
        $this->assertSame(42_000_000, $offer->price);
        $this->assertSame(50_000_000, $offer->compare_at_price);
    }

    /** Emptying the before-price box is how a sale is ended. */
    public function test_clearing_the_before_price_ends_the_sale(): void
    {
        $variant = $this->aSize();

        $this->actingAs($this->staff)->post(
            route('admin.product.variants.price', [$variant->product, $variant]),
            ['price' => '4200000', 'compare_at_price' => '5000000'],
        );

        $this->assertNotNull($this->offerFor($variant)->compare_at_price);

        $this->actingAs($this->staff)->post(
            route('admin.product.variants.price', [$variant->product, $variant]),
            ['price' => '4200000', 'compare_at_price' => ''],
        );

        $this->assertNull($this->offerFor($variant)->compare_at_price);
    }

    /**
     * The same refusal as the pricing screen, because it is the same rule.
     *
     * `branch_offers` has this as a CHECK, so without it somebody gets a
     * constraint violation instead of a sentence.
     */
    public function test_a_before_price_under_the_price_is_refused_here_too(): void
    {
        $variant = $this->aSize();
        $was = $this->offerFor($variant)->price;

        $this->actingAs($this->staff)
            ->post(route('admin.product.variants.price', [$variant->product, $variant]), [
                'price' => '5000000',
                'compare_at_price' => '4000000',
            ])
            // The key is the box that is wrong, which is the half of a refusal
            // a form needs in order to light the right field.
            ->assertSessionHasErrors('compare_at_price');

        $this->assertSame($was, $this->offerFor($variant)->price, 'The refused price was written anyway.');
    }

    /** The row is on the screen, with both boxes, where somebody can find it. */
    public function test_the_product_screen_draws_the_price_boxes(): void
    {
        $variant = $this->aSize();

        $this->actingAs($this->staff)
            ->get(route('admin.product.edit', $variant->product))
            ->assertOk()
            ->assertSee(route('admin.product.variants.price', [$variant->product, $variant]), false)
            ->assertSee('قیمت قبل از تخفیف', false);
    }

    /**
     * The price cell names its own two boxes, so the phone's card layout must
     * not add the column's name between them.
     *
     * `partials/admin-scripts.blade.php` labels each cell from its column
     * heading; an empty `data-label` is the stylesheet's own way of saying «no
     * label here», and the script now leaves a cell that carries one alone.
     * Asserted in the markup because nothing else can see it: the table is a
     * plain table above 992 and only becomes cards below it.
     */
    public function test_the_price_cell_keeps_its_own_labels_on_a_phone(): void
    {
        $variant = $this->aSize();

        $this->actingAs($this->staff)
            ->get(route('admin.product.edit', $variant->product))
            ->assertOk()
            ->assertSee('<td data-label="">', false);
    }

    /** A price for a size this branch does not sell is refused, not invented. */
    public function test_a_size_with_no_offer_here_says_so(): void
    {
        $variant = $this->aSize();

        app(TenantContext::class)->forBranch(
            Branch::central(),
            fn () => BranchOffer::where('variant_id', $variant->id)->delete(),
        );

        $this->actingAs($this->staff)
            ->post(route('admin.product.variants.price', [$variant->product, $variant]), ['price' => '4200000'])
            ->assertSessionHasErrors('price');
    }

    /**
     * **A product made in the panel is on the shop.**
     *
     * The form's date box came up empty, so `store()` saved a null
     * `published_at`, and `purchasable()` wants a date in the past: the shoe
     * was «فعال» in the panel and invisible on the site, with nothing anywhere
     * saying so.
     */
    public function test_a_new_product_opens_published(): void
    {
        $this->actingAs($this->staff)
            ->get(route('admin.product.create'))
            ->assertOk()
            ->assertSee('value="'.now()->format('Y-m-d').'"', false);
    }

    /** And the date the form offers is the one that gets saved. */
    public function test_creating_a_product_with_the_form_as_it_comes_puts_it_on_the_shop(): void
    {
        $this->actingAs($this->staff)->post(route('admin.product.store'), [
            'title' => 'کتونی زنانه نایک مدل Air Force 1 سفید',
            'status' => 'active',
            'published_at' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $product = Product::withoutGlobalScopes()->where('title', 'کتونی زنانه نایک مدل Air Force 1 سفید')->firstOrFail();

        $this->assertNotNull($product->published_at, 'A product added from the panel is not published.');
        $this->assertTrue($product->published_at->isPast() || $product->published_at->isToday());
    }

    /**
     * Clearing the date on an existing product still takes it off the shop.
     *
     * That is the reason the fix is a value in the form rather than a default
     * in `store()`: a store that filled the date in behind somebody's back
     * would make this impossible to do.
     */
    public function test_an_empty_date_still_takes_a_product_off_the_shop(): void
    {
        $product = Product::where('slug', 'new-balance-530')->firstOrFail();

        $this->actingAs($this->staff)->post(route('admin.product.update', $product), [
            'title' => $product->title,
            'status' => 'active',
            'published_at' => '',
        ])->assertRedirect();

        $this->assertNull($product->fresh()->published_at);
    }

    /**
     * «غیرفعال» is a switch that works, in both directions.
     *
     * It used to post `inactive`, which `products.status` refuses — a CHECK
     * violation, so choosing it was a 500. It is how the sandals retired on
     * 2026-09-24 come back, one at a time, so both halves are held here.
     */
    public function test_the_status_select_takes_a_product_off_and_puts_it_back(): void
    {
        $product = Product::where('slug', 'new-balance-530')->firstOrFail();
        $published = $product->published_at->toDateString();

        foreach (['archived', 'active'] as $status) {
            $this->actingAs($this->staff)->post(route('admin.product.update', $product), [
                'title' => $product->title,
                'status' => $status,
                'published_at' => $published,
            ])->assertRedirect()->assertSessionHasNoErrors();

            $this->assertSame($status, $product->fresh()->status);
        }

        $this->actingAs($this->staff)->get(route('admin.product.edit', $product))
            ->assertOk()
            ->assertSee('<option value="archived"', false)
            ->assertDontSee('value="inactive"', false);
    }
}
