<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\MessageAttachment;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\Branches\BranchOpener;
use App\Support\Messaging\Thread;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * §11–§13: the shop and its customer, talking.
 *
 * What is held down here is not «can two rows be written» but the four things
 * that make a messaging system either trustworthy or a leak:
 *
 *   1. A thread belongs to a branch and to one customer, and neither an id in
 *      a URL nor an order number in a form can cross either line.
 *   2. «Unread» is per person. Two members of staff read one inbox.
 *   3. An attachment is not on the public disk. A photograph of a receipt with
 *      somebody's address on it must not have a URL that works for anybody who
 *      guesses it.
 *   4. Closing a thread does not silence the customer — a reply re-opens it,
 *      or the next question lands where nobody is looking.
 */
class MessagingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);

        $this->branch = Branch::central();
        $this->customer = Customer::create(['phone' => '09121110000', 'name' => 'زهرا', 'is_active' => true]);
    }

    private function staff(string $email = 'staff@vikyplus.test'): User
    {
        $user = User::create(['name' => 'کارمند', 'email' => $email, 'password' => 'secret']);
        $user->roles()->attach(Role::where('slug', Role::ADMIN)->sole());

        return $user;
    }

    /**
     * How many threads this person has not caught up with, **inside the
     * branch**.
     *
     * Conversation is branch-scoped and fails closed, so `unreadByStaff()`
     * called with nothing bound returns zero — and an assertion of zero would
     * then pass for the wrong reason. CLAUDE.md records this trap the other
     * way round; this is the same trap, and the reason every count here goes
     * through one helper that binds the branch explicitly rather than relying
     * on a binding some earlier HTTP call left in the container.
     */
    private function unread(User $user): int
    {
        return app(TenantContext::class)->forBranch(
            $this->branch,
            fn (): int => Conversation::unreadByStaff($user->id)->count()
        );
    }

    private function thread(?Order $order = null, ?Branch $branch = null): Conversation
    {
        return app(TenantContext::class)->forBranch($branch ?? $this->branch, fn () => app(Thread::class)
            ->start($this->customer, 'تعویض سایز', 'سایز ۳۸ برایم کوچک بود.', $order));
    }

    // --- writing -----------------------------------------------------------

    public function test_a_customer_can_start_a_thread_and_it_lands_in_the_branch(): void
    {
        $conversation = $this->thread();

        $this->assertSame($this->branch->id, $conversation->branch_id);
        $this->assertSame($this->customer->id, $conversation->customer_id);
        $this->assertSame(Conversation::OPEN, $conversation->status);
        $this->assertCount(1, $conversation->messages);
    }

    /**
     * The inbox is sorted by it, so it has to be written by whatever writes a
     * message rather than by whoever remembers. It is set in the same
     * transaction as the message it describes.
     */
    public function test_the_last_message_time_moves_with_every_line(): void
    {
        $conversation = $this->thread();
        $first = $conversation->last_message_at;

        $this->travel(2)->minutes();

        app(TenantContext::class)->forBranch($this->branch, fn () => app(Thread::class)
            ->reply($conversation, $this->staff(), 'سایز ۳۹ را برایتان کنار گذاشتیم.'));

        $this->assertTrue($conversation->refresh()->last_message_at->greaterThan($first));
    }

    // --- who may read it ---------------------------------------------------

    /** A thread is the branch's; another branch's manager cannot open it. */
    public function test_a_thread_is_invisible_from_another_branch(): void
    {
        $shiraz = app(BranchOpener::class)->open('shiraz', 'ویکی پلاس شیراز', openingStock: 2);

        $theirs = $this->thread(branch: $shiraz);

        // A platform admin standing in the central branch: the scope, not the
        // permission, is what refuses this.
        $this->actingAs($this->staff(), 'web')
            ->get('/admin/inbox/'.$theirs->id)
            ->assertNotFound();
    }

    /** And one customer cannot read another's, however the id is reached. */
    public function test_a_customer_cannot_open_somebody_elses_thread(): void
    {
        $conversation = $this->thread();

        $other = Customer::create(['phone' => '09129990000', 'is_active' => true]);

        $this->actingAs($other, 'customer')
            ->get('/account/messages/'.$conversation->id)
            ->assertNotFound();
    }

    /**
     * The order number arrives in a form, so it is a number somebody can
     * change. It is checked against the signed-in customer, not only found.
     */
    public function test_a_thread_cannot_be_attached_to_somebody_elses_order(): void
    {
        $stranger = Customer::create(['phone' => '09128880000', 'is_active' => true]);

        $order = app(TenantContext::class)->forBranch($this->branch, fn () => Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => $stranger->id,
            'number' => 'VP-999111',
            'status' => Order::PLACED, 'payment_status' => 'unpaid',
            'subtotal' => 500_000, 'discount_total' => 0, 'shipping_total' => 0,
            'grand_total' => 500_000,
            'contact_name' => 'دیگری', 'contact_phone' => '09128880000',
            'address' => 'ن', 'placed_at' => now(),
        ]));

        $this->actingAs($this->customer, 'customer')
            ->post('/account/messages', [
                'subject' => 'درباره سفارش',
                'body' => 'سلام',
                'order' => $order->number,
            ])
            ->assertSessionHasErrors('order');

        $this->assertSame(0, Conversation::acrossAllBranches()->count());
    }

    // --- unread ------------------------------------------------------------

    /**
     * **Unread is per person.** Two people work one inbox; one of them opening
     * a thread must not tell the other there is nothing new.
     */
    public function test_one_member_of_staff_reading_a_thread_does_not_mark_it_read_for_another(): void
    {
        $conversation = $this->thread();

        $first = $this->staff('one@vikyplus.test');
        $second = $this->staff('two@vikyplus.test');

        $this->actingAs($first, 'web')->get('/admin/inbox/'.$conversation->id)->assertOk();

        $this->assertSame(0, $this->unread($first));
        $this->assertSame(1, $this->unread($second));
    }

    /**
     * **A thread another person answered is still unread to me.**
     *
     * This is the case the obvious rule gets wrong. «Has been spoken in since
     * I last looked, and the last word was not mine» reads as correct and is
     * not: a customer writes, a colleague answers, and now the last word is
     * the colleague's — so the thread reads as caught-up to somebody who never
     * saw the customer's question at all. Counting against the cursor's own
     * message id has no such gap.
     */
    public function test_a_colleagues_reply_does_not_hide_a_question_i_never_read(): void
    {
        $conversation = $this->thread();

        $mine = $this->staff('mine@vikyplus.test');
        $colleague = $this->staff('theirs@vikyplus.test');

        app(TenantContext::class)->forBranch($this->branch, fn () => app(Thread::class)
            ->reply($conversation, $colleague, 'بررسی می‌کنیم.'));

        $this->assertSame(1, $this->unread($mine));
        $this->assertSame(0, $this->unread($colleague));
    }

    /** Writing is reading: a reply must not light up its own author's badge. */
    public function test_replying_does_not_leave_the_thread_unread_for_the_person_who_replied(): void
    {
        $conversation = $this->thread();
        $user = $this->staff();

        app(TenantContext::class)->forBranch($this->branch, fn () => app(Thread::class)
            ->reply($conversation, $user, 'همین امروز می‌فرستیم.'));

        $this->assertSame(0, $this->unread($user));
    }

    /** And the customer's own count works the same way round. */
    public function test_the_customer_sees_a_reply_as_unread_until_they_open_it(): void
    {
        $conversation = $this->thread();

        app(TenantContext::class)->forBranch($this->branch, fn () => app(Thread::class)
            ->reply($conversation, $this->staff(), 'سایز ۳۹ موجود است.'));

        $this->assertSame(1, $conversation->fresh()->load('participants')
            ->unreadFor(Conversation::CUSTOMER, $this->customer->id));

        $this->actingAs($this->customer, 'customer')
            ->get('/account/messages/'.$conversation->id)->assertOk();

        $this->assertSame(0, $conversation->fresh()->load('participants')
            ->unreadFor(Conversation::CUSTOMER, $this->customer->id));
    }

    // --- attachments -------------------------------------------------------

    /**
     * **Not the public disk.**
     *
     * A photograph of a damaged parcel or of a receipt is the customer's. On
     * the `public` disk it would have a URL that works for anybody who guesses
     * it and asks nobody to sign in. This asserts the file is on the private
     * disk and *not* on the public one — the second half matters, because a
     * change that writes both would pass the first.
     */
    public function test_an_attachment_is_stored_off_the_public_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->actingAs($this->customer, 'customer')
            ->post('/account/messages', [
                'subject' => 'کفش آسیب دیده',
                'body' => 'عکس را ببینید.',
                'files' => [UploadedFile::fake()->image('damage.jpg')],
            ])
            ->assertRedirect();

        $attachment = MessageAttachment::sole();

        $this->assertTrue(Storage::disk('local')->exists($attachment->path));
        $this->assertFalse(Storage::disk('public')->exists($attachment->path));
        $this->assertStringStartsWith('attachments/', $attachment->path);
    }

    /** It is served by a route that checks the thread, not by a bare URL. */
    public function test_a_stranger_cannot_download_an_attachment(): void
    {
        Storage::fake('local');

        $this->actingAs($this->customer, 'customer')->post('/account/messages', [
            'subject' => 'رسید', 'body' => 'بفرمایید.',
            'files' => [UploadedFile::fake()->image('receipt.jpg')],
        ]);

        $conversation = Conversation::acrossAllBranches()->sole();
        $attachment = MessageAttachment::sole();

        $stranger = Customer::create(['phone' => '09127770000', 'is_active' => true]);

        $this->actingAs($stranger, 'customer')
            ->get("/account/messages/{$conversation->id}/files/{$attachment->id}")
            ->assertNotFound();
    }

    /** The name a person gave a file is kept, and never used as a path. */
    public function test_the_uploaded_name_is_kept_but_the_stored_path_is_ours(): void
    {
        Storage::fake('local');

        $this->actingAs($this->customer, 'customer')->post('/account/messages', [
            'subject' => 'عکس', 'body' => 'این هم عکس.',
            'files' => [UploadedFile::fake()->image('../../etc/passwd.jpg')],
        ]);

        $attachment = MessageAttachment::sole();

        $this->assertStringNotContainsString('..', $attachment->path);
        $this->assertStringStartsWith('attachments/', $attachment->path);
    }

    // --- closing -----------------------------------------------------------

    /**
     * **Closing is not silencing.** A customer with one more question writes in
     * a closed thread, and it has to come back to where somebody is looking.
     */
    public function test_a_customers_reply_reopens_a_closed_thread(): void
    {
        $conversation = $this->thread();

        app(TenantContext::class)->forBranch($this->branch, function () use ($conversation): void {
            $conversation->update(['status' => Conversation::CLOSED]);
        });

        $this->actingAs($this->customer, 'customer')
            ->post('/account/messages/'.$conversation->id, ['body' => 'یک سؤال دیگر هم داشتم.'])
            ->assertRedirect();

        $this->assertSame(Conversation::OPEN, $conversation->fresh()->status);
    }

    /** A line the application wrote does not re-open anything. */
    public function test_a_system_note_leaves_a_closed_thread_closed(): void
    {
        $conversation = $this->thread();

        app(TenantContext::class)->forBranch($this->branch, function () use ($conversation): void {
            $conversation->update(['status' => Conversation::CLOSED]);
            app(Thread::class)->note($conversation, 'سفارش تحویل داده شد.');
        });

        $this->assertSame(Conversation::CLOSED, $conversation->fresh()->status);
    }

    // --- the screens -------------------------------------------------------

    public function test_the_inbox_lists_the_branchs_threads_and_the_panel_offers_it(): void
    {
        $this->thread();

        $this->actingAs($this->staff(), 'web')->get('/admin/inbox')
            ->assertOk()
            ->assertSee('تعویض سایز')
            ->assertSee('زهرا');

        // And it is in the navigation, so nobody has to know the address.
        $this->actingAs($this->staff('two@vikyplus.test'), 'web')->get('/admin')
            ->assertOk()
            ->assertSee(route('admin.inbox'), false);
    }

    public function test_the_customer_sees_their_own_threads_and_nobody_elses(): void
    {
        $this->thread();

        $other = Customer::create(['phone' => '09126660000', 'is_active' => true]);
        app(TenantContext::class)->forBranch($this->branch, fn () => app(Thread::class)
            ->start($other, 'سؤال دیگری', 'سلام'));

        $this->actingAs($this->customer, 'customer')->get('/account/messages')
            ->assertOk()
            ->assertSee('تعویض سایز')
            ->assertDontSee('سؤال دیگری');
    }

    /** A thread is a history, so it asks for a sign-in rather than a session. */
    public function test_a_guest_is_sent_to_the_shoppers_sign_in(): void
    {
        $this->get('/account/messages')->assertRedirect(route('account.enter'));
    }

    /** The bell counts it, which is the one thing on it that remembers. */
    public function test_an_unread_thread_reaches_the_panels_bell(): void
    {
        $this->thread();

        $this->actingAs($this->staff(), 'web')->get('/admin')
            ->assertOk()
            ->assertSee('پیام خوانده‌نشده');
    }
}
