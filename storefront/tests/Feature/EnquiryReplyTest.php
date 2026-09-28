<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use App\Models\Role;
use App\Models\User;
use App\Support\Sms\Sender;
use Database\Seeders\BranchSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * «چرا هیچ قسمتی برای پاسخ دادن به پیام نداریم تو پنل ادمین؟؟؟؟»
 *
 * An enquiry is answered from the panel, by text message to the number it was
 * left with, and the answer stays under it.
 */
class EnquiryReplyTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{0: string, 1: string, 2: list<string>, 3: string}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class]);

        $this->app->instance(Sender::class, new class($this->sent) implements Sender
        {
            public function __construct(private array &$sent) {}

            public function send(string $phone, string $message, array $args = [], string $purpose = self::CODE): void
            {
                $this->sent[] = [$phone, $message, $args, $purpose];
            }
        });
    }

    private function owner(): User
    {
        $user = User::factory()->create(['name' => 'سجاد']);
        $user->roles()->attach(Role::where('slug', Role::OWNER)->sole());

        return $user;
    }

    private function enquiry(): Enquiry
    {
        return Enquiry::create([
            'kind' => Enquiry::SUPPORT,
            'name' => 'مریم',
            'phone' => '09123456789',
            'message' => 'سفارشم نرسیده',
        ]);
    }

    public function test_every_enquiry_has_a_reply_box(): void
    {
        $enquiry = $this->enquiry();

        $this->actingAs($this->owner(), 'web')
            ->get('/admin/enquiries')
            ->assertOk()
            ->assertSee(route('admin.enquiry.reply', $enquiry), false)
            ->assertSee('name="body"', false)
            ->assertSee('ارسال پاسخ');
    }

    public function test_a_reply_goes_by_text_and_stays_under_the_enquiry(): void
    {
        $enquiry = $this->enquiry();

        $this->actingAs($this->owner(), 'web')
            ->post("/admin/enquiries/{$enquiry->id}/reply", ['body' => 'فردا ارسال می‌شود'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertCount(1, $this->sent);
        [$phone, $message, $args, $purpose] = $this->sent[0];
        $this->assertSame('09123456789', $phone);
        $this->assertStringContainsString('فردا ارسال می‌شود', $message);
        $this->assertSame(['فردا ارسال می‌شود'], $args);
        $this->assertSame(Sender::REPLY, $purpose);

        $reply = $enquiry->replies()->sole();
        $this->assertSame('فردا ارسال می‌شود', $reply->body);
        $this->assertNotNull($reply->sent_at);

        // Answered is contacted.
        $this->assertSame(Enquiry::CONTACTED, $enquiry->fresh()->status);
        $this->assertNotNull($enquiry->fresh()->handled_by);

        $this->get('/admin/enquiries')
            ->assertSee('فردا ارسال می‌شود')
            ->assertSee('با پیامک فرستاده شد');
    }

    public function test_a_closed_enquiry_stays_closed_when_answered(): void
    {
        $enquiry = $this->enquiry();
        $enquiry->update(['status' => Enquiry::CLOSED]);

        $this->actingAs($this->owner(), 'web')
            ->post("/admin/enquiries/{$enquiry->id}/reply", ['body' => 'یک نکته دیگر']);

        $this->assertSame(Enquiry::CLOSED, $enquiry->fresh()->status);
    }

    public function test_a_message_that_could_not_leave_is_kept_and_says_so(): void
    {
        $this->app->instance(Sender::class, new class implements Sender
        {
            public function send(string $phone, string $message, array $args = [], string $purpose = self::CODE): void
            {
                throw new RuntimeException('SMS_PATTERN_REPLY is empty');
            }
        });

        $enquiry = $this->enquiry();

        $this->actingAs($this->owner(), 'web')
            ->post("/admin/enquiries/{$enquiry->id}/reply", ['body' => 'سلام'])
            ->assertRedirect()
            ->assertSessionHasErrors('body');

        $reply = $enquiry->replies()->sole();
        $this->assertNull($reply->sent_at);
        $this->assertStringContainsString('SMS_PATTERN_REPLY', $reply->failure);

        $this->get('/admin/enquiries')->assertSee('پیامک فرستاده نشد');
    }

    public function test_an_empty_reply_is_refused(): void
    {
        $enquiry = $this->enquiry();

        $this->actingAs($this->owner(), 'web')
            ->post("/admin/enquiries/{$enquiry->id}/reply", ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, $enquiry->replies()->count());
        $this->assertSame([], $this->sent);
    }

    public function test_somebody_without_the_permission_cannot_reply(): void
    {
        $enquiry = $this->enquiry();
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('slug', Role::MARKETPLACE_MANAGER)->sole());

        $this->actingAs($staff, 'web')
            ->post("/admin/enquiries/{$enquiry->id}/reply", ['body' => 'سلام'])
            ->assertForbidden();

        $this->assertSame([], $this->sent);
    }

    /** «کادر تو کادر»: the screen is a card per enquiry, not a table in a card. */
    public function test_the_screen_is_not_a_table_in_a_card(): void
    {
        $this->enquiry();

        $page = $this->actingAs($this->owner(), 'web')->get('/admin/enquiries')->getContent();

        $this->assertStringNotContainsString('class="vp-admin-table', $page);
        $this->assertStringContainsString('vp-adm-card vp-adm-enq', $page);
    }
}
