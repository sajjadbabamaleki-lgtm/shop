<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductComment;
use App\Models\Role;
use App\Models\User;
use App\Models\Variant;
use App\Support\Branches\BranchOpener;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «نظر خریداران» — the band under the budget shelf.
 *
 * «همچنین یه جایی برای کامنت های مرتبط با اون کفش میخوایم», open to «فقط کسی
 * که خریده».
 *
 * Two rules carry the whole feature and neither of them shows on a screenshot,
 * which is why they are tested rather than looked at:
 *
 * **Only a buyer may write**, and «buyer» means this account has a paid order
 * carrying this shoe — not that they are signed in, and not that they have an
 * order. The two gates are different questions.
 *
 * **Nothing reaches the page until somebody has read it.** Every comment is
 * stored `pending`, and `/admin/comments` is the only thing that moves one. A
 * regression on that would look like a working feature from the customer's
 * side and would put whatever anybody typed on a public page.
 *
 * The panel screen is tested beside the form for the same reason `EnquiriesTest`
 * does it: the queue is not an extra, it is the half that decides.
 */
class ProductCommentsTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'nike-v2k-run';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);
    }

    private function shoe(string $slug = self::SLUG): Product
    {
        return Product::where('slug', $slug)->firstOrFail();
    }

    private function customer(string $phone = '09123456789', ?string $name = 'مریم'): Customer
    {
        return Customer::create(['name' => $name, 'phone' => $phone, 'password' => 'password-1234']);
    }

    /**
     * An order for this customer carrying this shoe, at this branch, in this
     * state — the four things every case here varies.
     */
    private function bought(Customer $customer, Product $product, string $status = Order::PAID, ?Branch $branch = null): Order
    {
        $branch ??= Branch::central();

        return app(TenantContext::class)->forBranch($branch, function () use ($customer, $product, $status, $branch): Order {
            $order = Order::create([
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'number' => 'VP-'.mt_rand(100000, 999999),
                'status' => $status,
                'subtotal' => 1_000_000,
                'discount_total' => 0,
                'shipping_total' => 0,
                'grand_total' => 1_000_000,
                'contact_name' => $customer->name ?? 'خریدار',
                'contact_phone' => $customer->phone,
                'address' => 'نشانی آزمایشی',
                'placed_at' => now(),
            ]);

            $variant = $product->variants()->firstOrFail();

            OrderItem::create([
                'order_id' => $order->id,
                'variant_id' => $variant->id,
                'product_title' => $product->title,
                'sku' => $variant->sku,
                'size_value' => $variant->size_value,
                'unit_price' => 1_000_000,
                'quantity' => 1,
                'line_total' => 1_000_000,
            ]);

            return $order;
        });
    }

    private function platformAdmin(): User
    {
        $user = User::create(['name' => 'مدیر', 'email' => 'panel@vikyplus.test', 'password' => 'secret']);
        $user->roles()->attach(Role::where('slug', Role::ADMIN)->sole());

        return $user;
    }

    // --- who may write ----------------------------------------------------

    /**
     * The band is drawn on every product page, with nothing in it.
     *
     * A shop that hides the heading until somebody has written can never get
     * the first comment: the person who bought the shoe has no way of knowing
     * the page would take one.
     */
    public function test_the_band_is_there_before_anybody_has_written(): void
    {
        $this->get('/products/'.self::SLUG)
            ->assertOk()
            ->assertSee('نظر خریداران', false)
            ->assertSee('هنوز کسی دربارهٔ این کفش ننوشته است.', false);
    }

    public function test_a_guest_is_offered_the_shoppers_sign_in_and_no_form(): void
    {
        $this->get('/products/'.self::SLUG)
            ->assertOk()
            ->assertSee(route('account.enter'), false)
            ->assertDontSee('name="body"', false);
    }

    /** Signed in is not the same thing as having bought it. */
    public function test_somebody_who_has_not_bought_it_gets_no_form(): void
    {
        $this->actingAs($this->customer(), 'customer')
            ->get('/products/'.self::SLUG)
            ->assertOk()
            ->assertSee('نوشتن نظر برای کسانی است که این کفش را خریده‌اند.', false)
            ->assertDontSee('name="body"', false);
    }

    /** Nor is having *an* order the same as having bought *this* shoe. */
    public function test_a_buyer_of_something_else_gets_no_form(): void
    {
        $customer = $this->customer();
        $this->bought($customer, $this->shoe('on-cloudtilt'));

        $this->actingAs($customer, 'customer')
            ->get('/products/'.self::SLUG)
            ->assertOk()
            ->assertDontSee('name="body"', false);
    }

    /**
     * An unpaid order is an intention. A basket somebody filled in and walked
     * away from must not buy the right to write on a public page.
     */
    public function test_an_unpaid_order_does_not_count_as_having_bought_it(): void
    {
        $customer = $this->customer();
        $this->bought($customer, $this->shoe(), Order::PLACED);

        $this->actingAs($customer, 'customer')
            ->get('/products/'.self::SLUG)
            ->assertOk()
            ->assertDontSee('name="body"', false);
    }

    public function test_a_buyer_gets_the_form(): void
    {
        $customer = $this->customer();
        $this->bought($customer, $this->shoe());

        $this->actingAs($customer, 'customer')
            ->get('/products/'.self::SLUG)
            ->assertOk()
            ->assertSee('name="body"', false);
    }

    /**
     * Bought at a franchise, read on the main store — still their shoe.
     *
     * Orders are branch-scoped and rightly so; comments are not, because the
     * shoe is the same shoe at every branch. Scoping the purchase check would
     * tell somebody who paid at Shiraz that they had never bought it.
     */
    public function test_buying_at_another_branch_still_counts(): void
    {
        $shiraz = app(BranchOpener::class)->open('shiraz', 'ویکی پلاس شیراز', openingStock: 1);

        $customer = $this->customer();
        $this->bought($customer, $this->shoe(), Order::PAID, $shiraz);

        $this->actingAs($customer, 'customer')
            ->get('/products/'.self::SLUG)
            ->assertOk()
            ->assertSee('name="body"', false);
    }

    public function test_a_guest_posting_is_sent_to_the_shoppers_sign_in(): void
    {
        // Not to /admin/login, which is a form their account cannot satisfy.
        $this->post('/products/'.self::SLUG.'/comments', ['body' => 'کفش خوبی بود و اندازه پایم شد.', 'rating' => 5])
            ->assertRedirect(route('account.enter'));

        $this->assertSame(0, ProductComment::count());
    }

    /**
     * The gate is on the write and not only on the drawing of the form. A
     * stale page or a second tab is the ordinary way this is reached.
     */
    public function test_somebody_who_has_not_bought_it_cannot_write_one(): void
    {
        $this->actingAs($this->customer(), 'customer')
            ->post('/products/'.self::SLUG.'/comments', ['body' => 'کفش خوبی بود و اندازه پایم شد.', 'rating' => 5])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, ProductComment::count());
    }

    // --- what reaches the page --------------------------------------------

    /** Written, stored waiting, and not on the page. */
    public function test_a_comment_waits_for_the_shop_before_it_is_printed(): void
    {
        $customer = $this->customer();
        $this->bought($customer, $this->shoe());

        $this->actingAs($customer, 'customer')
            ->post('/products/'.self::SLUG.'/comments', ['body' => 'خیلی راحت است و سایزش درست بود.', 'rating' => 4])
            ->assertSessionHasNoErrors();

        $comment = ProductComment::sole();
        $this->assertSame(ProductComment::PENDING, $comment->status);
        $this->assertNull($comment->approved_at);

        /*
         * Read by somebody else, deliberately. The writer *does* see their own
         * sentence — it is sitting in the form's textarea, ready to be
         * rewritten, which is the whole point of the edit path — so asserting
         * this on their own request would pass for the wrong reason today and
         * fail for the wrong reason tomorrow. What is being tested is that a
         * comment nobody has read is not on the page, and the page is what a
         * visitor sees.
         */
        $this->app['auth']->guard('customer')->logout();

        $this->get('/products/'.self::SLUG)
            ->assertOk()
            ->assertDontSee('خیلی راحت است و سایزش درست بود.', false);
    }

    /** And the person who wrote it is told that it is waiting. */
    public function test_the_writer_is_told_their_comment_is_waiting(): void
    {
        $customer = $this->customer();
        $this->bought($customer, $this->shoe());

        ProductComment::create([
            'product_id' => $this->shoe()->id,
            'customer_id' => $customer->id,
            'body' => 'خیلی راحت است و سایزش درست بود.',
        ]);

        $this->actingAs($customer, 'customer')
            ->get('/products/'.self::SLUG)
            ->assertOk()
            ->assertSee('نظر شما ثبت شده و پس از بررسی منتشر می‌شود.', false);
    }

    public function test_an_approved_comment_is_printed_and_a_rejected_one_never_is(): void
    {
        $shoe = $this->shoe();

        ProductComment::create([
            'product_id' => $shoe->id,
            'customer_id' => $this->customer('09120000001', 'زهرا')->id,
            'body' => 'چرمش نرم است و بعد از یک ماه هنوز نو مانده.',
            'status' => ProductComment::PUBLISHED,
            'approved_at' => now(),
        ]);

        ProductComment::create([
            'product_id' => $shoe->id,
            'customer_id' => $this->customer('09120000002', 'بهاره')->id,
            'body' => 'این یکی نباید روی سایت بیاید.',
            'status' => ProductComment::REJECTED,
        ]);

        $this->get('/products/'.self::SLUG)
            ->assertOk()
            ->assertSee('چرمش نرم است و بعد از یک ماه هنوز نو مانده.', false)
            ->assertSee('زهرا', false)
            ->assertDontSee('این یکی نباید روی سایت بیاید.', false);
    }

    /**
     * A comment belongs to its own shoe.
     *
     * Trivial to get right and silent to get wrong: a relation without its
     * `product_id` condition would print every comment in the shop on every
     * page, and every one of them would be a real comment somebody wrote.
     */
    public function test_a_comment_stays_on_the_shoe_it_is_about(): void
    {
        ProductComment::create([
            'product_id' => $this->shoe('on-cloudtilt')->id,
            'customer_id' => $this->customer()->id,
            'body' => 'این نظر دربارهٔ کفش دیگری است.',
            'status' => ProductComment::PUBLISHED,
            'approved_at' => now(),
        ]);

        $this->get('/products/'.self::SLUG)
            ->assertOk()
            ->assertDontSee('این نظر دربارهٔ کفش دیگری است.', false);

        $this->get('/products/on-cloudtilt')
            ->assertOk()
            ->assertSee('این نظر دربارهٔ کفش دیگری است.', false);
    }

    /**
     * The number a customer signs in with does not go on a public page.
     *
     * A `customers` row is made at checkout off a telephone number and the name
     * is optional, so «no name» is the ordinary case rather than the edge one —
     * which is exactly when a naive fallback prints the number.
     */
    public function test_a_nameless_buyer_is_not_named_by_their_telephone_number(): void
    {
        ProductComment::create([
            'product_id' => $this->shoe()->id,
            'customer_id' => $this->customer('09121112233', null)->id,
            'body' => 'اندازه‌اش درست بود و زود رسید.',
            'status' => ProductComment::PUBLISHED,
            'approved_at' => now(),
        ]);

        $this->get('/products/'.self::SLUG)
            ->assertOk()
            ->assertSee('اندازه‌اش درست بود و زود رسید.', false)
            ->assertDontSee('09121112233', false)
            ->assertSee('0912****233', false);
    }

    /**
     * A second submission edits the first and puts it back in the queue.
     *
     * The queue would be a formality otherwise: approve a sentence about the
     * leather, come back and replace it with anything at all.
     */
    public function test_writing_again_edits_the_first_and_sends_it_back_to_be_read(): void
    {
        $customer = $this->customer();
        $this->bought($customer, $this->shoe());

        $comment = ProductComment::create([
            'product_id' => $this->shoe()->id,
            'customer_id' => $customer->id,
            'body' => 'اولین چیزی که نوشتم.',
            'status' => ProductComment::PUBLISHED,
            'approved_at' => now(),
        ]);

        $this->actingAs($customer, 'customer')
            ->post('/products/'.self::SLUG.'/comments', ['body' => 'بعد از یک ماه نظرم عوض شد و کفش پهن‌تر شد.', 'rating' => 2])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ProductComment::count());

        $comment->refresh();
        $this->assertSame('بعد از یک ماه نظرم عوض شد و کفش پهن‌تر شد.', $comment->body);
        $this->assertSame(ProductComment::PENDING, $comment->status);
        $this->assertNull($comment->approved_at);
    }

    /** «خوب بود» is not the paragraph a shopper came to read. */
    public function test_a_comment_too_short_to_be_one_is_refused(): void
    {
        $customer = $this->customer();
        $this->bought($customer, $this->shoe());

        $this->actingAs($customer, 'customer')
            ->post('/products/'.self::SLUG.'/comments', ['body' => 'خوب', 'rating' => 5])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, ProductComment::count());
    }

    // --- the stars --------------------------------------------------------

    /**
     * A score is required from the form, though the column is nullable.
     *
     * The column is nullable for the comments written before stars existed;
     * inventing a five for those would be putting a number in somebody's
     * mouth. Nothing written from here on may skip it.
     */
    public function test_a_comment_written_without_a_score_is_refused(): void
    {
        $customer = $this->customer();
        $this->bought($customer, $this->shoe());

        $this->actingAs($customer, 'customer')
            ->post('/products/'.self::SLUG.'/comments', ['body' => 'کفش خوبی بود و اندازه پایم شد.'])
            ->assertSessionHasErrors('rating');

        $this->assertSame(0, ProductComment::count());
    }

    /** And it has to be one of the five stars the page actually draws. */
    public function test_a_score_off_the_scale_is_refused(): void
    {
        $customer = $this->customer();
        $this->bought($customer, $this->shoe());

        foreach ([0, 6, -1] as $score) {
            $this->actingAs($customer, 'customer')
                ->post('/products/'.self::SLUG.'/comments', [
                    'body' => 'کفش خوبی بود و اندازه پایم شد.',
                    'rating' => $score,
                ])
                ->assertSessionHasErrors('rating');
        }

        $this->assertSame(0, ProductComment::count());
    }

    /**
     * The average is over the comments that carry a score, and it is absent
     * when none do.
     *
     * Five empty stars over «۰ از ۵» is a bad review the shop invented out of
     * an empty table, which is the one number a shop most wants to be true.
     */
    public function test_the_average_counts_only_what_was_scored(): void
    {
        $shoe = $this->shoe();

        $this->get('/products/'.self::SLUG)->assertOk()->assertViewHas('rating', null);

        ProductComment::create([
            'product_id' => $shoe->id,
            'customer_id' => $this->customer('09120000001', 'زهرا')->id,
            'body' => 'چرمش نرم است و بعد از یک ماه هنوز نو مانده.',
            'rating' => 5,
            'status' => ProductComment::PUBLISHED,
            'approved_at' => now(),
        ]);

        // Scored 4, so the average of the two is 4.5.
        ProductComment::create([
            'product_id' => $shoe->id,
            'customer_id' => $this->customer('09120000002', 'بهاره')->id,
            'body' => 'خوب بود ولی کمی تنگ است و یک سایز بالاتر بگیرید.',
            'rating' => 4,
            'status' => ProductComment::PUBLISHED,
            'approved_at' => now(),
        ]);

        // No score at all: it must not be counted as a nought.
        ProductComment::create([
            'product_id' => $shoe->id,
            'customer_id' => $this->customer('09120000003', 'نگار')->id,
            'body' => 'این یکی امتیازی ندارد و نباید میانگین را پایین بیاورد.',
            'status' => ProductComment::PUBLISHED,
            'approved_at' => now(),
        ]);

        $this->get('/products/'.self::SLUG)->assertOk()->assertViewHas('rating', 4.5);
    }

    /** The score survives the round trip and is what the page prints. */
    public function test_the_score_a_buyer_picks_is_the_one_that_is_stored(): void
    {
        $customer = $this->customer();
        $this->bought($customer, $this->shoe());

        $this->actingAs($customer, 'customer')
            ->post('/products/'.self::SLUG.'/comments', [
                'body' => 'سه ستاره؛ خوب است ولی پاشنه‌اش کمی سفت است.',
                'rating' => 3,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, ProductComment::sole()->rating);
    }

    // --- the queue --------------------------------------------------------

    public function test_the_panel_lists_what_is_waiting_and_publishes_it(): void
    {
        $customer = $this->customer();

        $comment = ProductComment::create([
            'product_id' => $this->shoe()->id,
            'customer_id' => $customer->id,
            'body' => 'کفش سبکی است و برای پیاده‌روی طولانی خوب بود.',
        ]);

        $panel = $this->platformAdmin();

        $this->actingAs($panel)
            ->get('/admin/comments')
            ->assertOk()
            ->assertSee('کفش سبکی است و برای پیاده‌روی طولانی خوب بود.', false)
            ->assertSee('در انتظار تأیید', false);

        $this->actingAs($panel)
            ->post('/admin/comments/'.$comment->id, ['status' => ProductComment::PUBLISHED])
            ->assertRedirect();

        // The shop's own page is read signed out, the way a visitor reads it.
        $this->app['auth']->guard('web')->logout();

        $comment->refresh();
        $this->assertSame(ProductComment::PUBLISHED, $comment->status);
        $this->assertNotNull($comment->approved_at);

        $this->get('/products/'.self::SLUG)
            ->assertOk()
            ->assertSee('کفش سبکی است و برای پیاده‌روی طولانی خوب بود.', false);
    }

    /** Sending one back to the queue takes it off the page with it. */
    public function test_rejecting_a_published_comment_takes_it_off_the_page(): void
    {
        $comment = ProductComment::create([
            'product_id' => $this->shoe()->id,
            'customer_id' => $this->customer()->id,
            'body' => 'این نظر منتشر شده بود و باید برداشته شود.',
            'status' => ProductComment::PUBLISHED,
            'approved_at' => now(),
        ]);

        $this->actingAs($this->platformAdmin())
            ->post('/admin/comments/'.$comment->id, ['status' => ProductComment::REJECTED])
            ->assertRedirect();

        $this->assertNull($comment->refresh()->approved_at);

        $this->get('/products/'.self::SLUG)
            ->assertOk()
            ->assertDontSee('این نظر منتشر شده بود و باید برداشته شود.', false);
    }

    /**
     * The permission is its own, and a role that does not hold it is refused.
     *
     * `platform.comment.manage` reaches production through a migration, not
     * through the seeder — production is not a fresh install. This covers the
     * seeder's half; `RolesAndPermissionsTest` counts the rest.
     */
    public function test_the_queue_is_behind_its_own_permission(): void
    {
        $this->get('/admin/comments')->assertRedirect(route('admin.login'));

        $staff = User::create(['name' => 'انباردار', 'email' => 'store@vikyplus.test', 'password' => 'secret']);
        $staff->roles()->attach(Role::where('slug', Role::MARKETPLACE_MANAGER)->sole());

        $this->actingAs($staff)->get('/admin/comments')->assertForbidden();
    }

    /** And it is offered in the panel's navigation to somebody who holds it. */
    public function test_the_queue_is_in_the_panels_navigation(): void
    {
        $this->actingAs($this->platformAdmin())
            ->get('/admin')
            ->assertOk()
            ->assertSee(route('admin.comments'), false);
    }

    /**
     * Every variant of the shoe counts, not only the one the page prices.
     *
     * Somebody buys a 38 and reads the page, which defaults to whatever the
     * catalogue's default variant is. A check written against one variant would
     * refuse most of the people it is meant to admit.
     */
    public function test_any_size_of_the_shoe_counts_as_having_bought_it(): void
    {
        $shoe = $this->shoe();
        $customer = $this->customer();

        $other = $shoe->variants()->where('id', '!=', $shoe->default_variant_id)->first();
        $this->assertInstanceOf(Variant::class, $other, 'The seeded shoe has one size, so this proves nothing.');

        $order = $this->bought($customer, $shoe);
        app(TenantContext::class)->forBranch(Branch::central(), fn () => OrderItem::where('order_id', $order->id)
            ->update(['variant_id' => $other->id]));

        $this->actingAs($customer, 'customer')
            ->get('/products/'.self::SLUG)
            ->assertOk()
            ->assertSee('name="body"', false);
    }
}
