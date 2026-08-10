<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\Concerns\RecordsAudits;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use LogicException;
use Tests\TestCase;

/**
 * A model that records itself, standing in for the ones spec §29 names —
 * branch prices, commission rates, settlements — none of which have tables
 * yet. It borrows the categories table so the trait is exercised against real
 * columns rather than a fixture schema.
 */
class AuditedCategory extends Model
{
    use RecordsAudits;

    protected $table = 'categories';

    protected $fillable = ['slug', 'name', 'position', 'is_active', 'seo_title'];

    /** @var list<string> */
    protected array $auditExcept = ['seo_title'];
}

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_who_did_what_to_which_row(): void
    {
        $user = User::factory()->create();
        Auth::guard('web')->login($user);

        $category = Customer::create(['phone' => '09123456789']);

        Audit::record('vendor.suspended', $category, ['status' => 'active'], ['status' => 'suspended']);

        $audit = Audit::firstOrFail();

        $this->assertSame('vendor.suspended', $audit->action);
        $this->assertSame($user->id, $audit->actor_id);
        $this->assertSame(User::class, $audit->actor_type);
        $this->assertSame($category->id, $audit->auditable_id);
        $this->assertSame(['status' => 'active'], $audit->old_values);
        $this->assertSame(['status' => 'suspended'], $audit->new_values);
    }

    /**
     * A scheduled job or a console command has no actor, and saying so is
     * better than attributing the change to nobody in particular.
     */
    public function test_an_unattended_change_records_no_actor(): void
    {
        Audit::record('reservation.expired');

        $this->assertNull(Audit::firstOrFail()->actor_id);
        $this->assertNull(Audit::firstOrFail()->actor_type);
    }

    public function test_a_customer_can_be_the_actor(): void
    {
        $customer = Customer::create(['phone' => '09123456789']);
        Auth::guard('customer')->login($customer);

        Audit::record('order.cancelled');

        $this->assertSame(Customer::class, Audit::firstOrFail()->actor_type);
    }

    /** The trail is worthless if it can be rewritten. */
    public function test_an_audit_row_cannot_be_changed(): void
    {
        $audit = Audit::record('vendor.suspended');

        $this->expectException(LogicException::class);

        $audit->update(['action' => 'nothing.happened']);
    }

    public function test_an_audit_row_cannot_be_deleted(): void
    {
        $audit = Audit::record('vendor.suspended');

        $this->expectException(LogicException::class);

        $audit->delete();
    }

    public function test_the_trait_records_creation_with_the_new_values(): void
    {
        $category = AuditedCategory::create(['slug' => 'sneaker', 'name' => 'ونس و کتونی']);

        $audit = Audit::firstOrFail();

        $this->assertSame('category.created', $audit->action);
        $this->assertSame($category->id, $audit->auditable_id);
        $this->assertSame('sneaker', $audit->new_values['slug']);
        $this->assertNull($audit->old_values);
    }

    /**
     * Both sides of the change, and only the columns that moved — an audit
     * trail that copies whole rows is one nobody reads.
     */
    public function test_the_trait_records_only_what_changed(): void
    {
        $category = AuditedCategory::create(['slug' => 'sneaker', 'name' => 'ونس و کتونی', 'position' => 1]);
        Audit::query()->delete();

        $category->update(['position' => 4]);

        $audit = Audit::firstOrFail();

        $this->assertSame('category.updated', $audit->action);
        $this->assertSame(['position' => 4], $audit->new_values);
        $this->assertSame(['position' => 1], $audit->old_values);
    }

    public function test_the_trait_writes_nothing_when_nothing_moved(): void
    {
        $category = AuditedCategory::create(['slug' => 'sneaker', 'name' => 'ونس و کتونی']);
        Audit::query()->delete();

        $category->update(['name' => 'ونس و کتونی']);

        $this->assertSame(0, Audit::count());
    }

    public function test_excluded_columns_stay_out_of_the_trail(): void
    {
        $category = AuditedCategory::create(['slug' => 'sneaker', 'name' => 'ونس و کتونی']);
        Audit::query()->delete();

        $category->update(['seo_title' => 'کتونی و ونس زنانه', 'position' => 2]);

        $audit = Audit::firstOrFail();

        $this->assertArrayNotHasKey('seo_title', $audit->new_values);
        $this->assertArrayHasKey('position', $audit->new_values);
    }
}
