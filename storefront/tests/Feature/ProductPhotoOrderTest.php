<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\VariantMedia;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Putting a shoe's photographs on it, and in order.
 *
 * «نباید عکسهای محصول دونه دونه از گالری بیان باید بشه همشو باهم سلکت کرد آورد
 * بعد همونجا شماره گذاری باشه که عکس اول کدوم باشه عکس دوم کدوم و قابلت جابجای
 * داشته باشه» — three things: many at once, numbered, and movable.
 *
 * **None of these routes had a test at all before this file.** The suite could
 * create a product and price it and never once asked what happens to the
 * photographs, which is the part the shop touches most and the part where
 * getting it wrong is silent: a wrong order draws the wrong shoe on a card,
 * and nothing goes red.
 *
 * `position` is the order and **number one is the main shot** — the two are
 * one fact here, because two answers to «which is first?» is how a gallery
 * comes to lead with one photograph while the card shows another.
 */
class ProductPhotoOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, BranchSeeder::class, CatalogueSeeder::class]);
        app(TenantContext::class)->set(Branch::central());

        $this->staff = User::factory()->create();
        $this->staff->roles()->attach(Role::where('slug', Role::ADMIN)->firstOrFail());
        $this->staff = $this->staff->fresh();

        $this->product = Product::where('slug', 'new-balance-530')->firstOrFail();
        $this->product->media()->delete();

        Storage::fake('public');
    }

    /** @return list<array{id: int, position: int, primary: bool}> */
    private function shots(): array
    {
        return VariantMedia::query()
            ->where('product_id', $this->product->id)
            ->orderBy('position')
            ->get()
            ->map(fn (VariantMedia $m) => [
                'id' => $m->id,
                'position' => $m->position,
                'primary' => (bool) $m->is_primary,
            ])
            ->all();
    }

    /** Put `$count` photographs on the shoe, in one upload, in order. */
    private function upload(int $count): void
    {
        $this->actingAs($this->staff)
            ->post(route('admin.product.media.store', $this->product), [
                'photos' => array_map(
                    fn (int $n) => UploadedFile::fake()->image("shot-{$n}.jpg", 900, 900),
                    range(1, $count),
                ),
            ])
            ->assertRedirect(route('admin.product.edit', $this->product));
    }

    /**
     * **Several at once, and the order they were picked in is kept.**
     * That is the whole of the request: a shoe with six shots was six uploads
     * and six page loads.
     */
    public function test_many_photographs_arrive_in_one_upload(): void
    {
        $this->upload(4);

        $shots = $this->shots();

        $this->assertCount(4, $shots, 'Only some of the photographs were stored.');
        $this->assertSame([1, 2, 3, 4], array_column($shots, 'position'));
        $this->assertSame([true, false, false, false], array_column($shots, 'primary'));
    }

    /** A second upload lands behind the first, rather than renumbering it. */
    public function test_a_later_upload_goes_to_the_back(): void
    {
        $this->upload(2);
        $first = array_column($this->shots(), 'id');

        $this->upload(2);
        $all = array_column($this->shots(), 'id');

        $this->assertSame($first, array_slice($all, 0, 2), 'The photographs already here were reshuffled.');
        $this->assertSame([1, 2, 3, 4], array_column($this->shots(), 'position'));
    }

    /** What the dragging posts: the whole list, in the new order. */
    public function test_the_whole_order_can_be_rewritten_at_once(): void
    {
        $this->upload(4);
        $ids = array_column($this->shots(), 'id');

        $wanted = [$ids[3], $ids[1], $ids[0], $ids[2]];

        $this->actingAs($this->staff)
            ->post(route('admin.product.media.order', $this->product), ['order' => implode(',', $wanted)]);

        $shots = $this->shots();

        $this->assertSame($wanted, array_column($shots, 'id'));
        $this->assertSame([1, 2, 3, 4], array_column($shots, 'position'));
        $this->assertSame([true, false, false, false], array_column($shots, 'primary'));
    }

    /**
     * **An order that is not the same set of photographs is refused whole.**
     *
     * The list comes from a field in a browser, so a tab left open while
     * another was used to delete a shot would post a list naming it — or,
     * worse, one that leaves a photograph out. Writing that partially would
     * drop a photograph off the product with nothing to say so.
     */
    public function test_an_order_that_is_not_the_same_photographs_is_refused(): void
    {
        $this->upload(3);
        $before = $this->shots();
        $ids = array_column($before, 'id');

        foreach ([
            'one missing' => implode(',', [$ids[2], $ids[0]]),
            'one that is not ours' => implode(',', [$ids[2], $ids[0], $ids[1], 999999]),
            'nonsense' => 'x,y,z',
        ] as $why => $order) {
            $this->actingAs($this->staff)
                ->post(route('admin.product.media.order', $this->product), ['order' => $order])
                ->assertRedirect(route('admin.product.edit', $this->product));

            $this->assertSame($before, $this->shots(), "An order with {$why} was written.");
        }
    }

    /** «اصلی کن» is one press for the thing dragging takes six. */
    public function test_make_it_the_main_shot_moves_it_to_the_front(): void
    {
        $this->upload(3);
        $ids = array_column($this->shots(), 'id');

        $this->actingAs($this->staff)
            ->post(route('admin.product.media.primary', [$this->product, $ids[2]]));

        $shots = $this->shots();

        $this->assertSame([$ids[2], $ids[0], $ids[1]], array_column($shots, 'id'));
        $this->assertSame([true, false, false], array_column($shots, 'primary'));
    }

    /** Deleting closes the gap, and something is always the main shot. */
    public function test_deleting_renumbers_what_is_left(): void
    {
        $this->upload(3);
        $ids = array_column($this->shots(), 'id');

        $this->actingAs($this->staff)
            ->post(route('admin.product.media.delete', [$this->product, $ids[0]]));

        $shots = $this->shots();

        $this->assertSame([$ids[1], $ids[2]], array_column($shots, 'id'));
        $this->assertSame([1, 2], array_column($shots, 'position'));
        $this->assertSame([true, false], array_column($shots, 'primary'));
    }

    /**
     * What the screen has to carry for the dragging to work at all.
     *
     * The order is the one thing on this panel that now needs JavaScript,
     * which was the shop's own choice — three buttons asking to be understood
     * were worse than picking a tile up. So the pieces it depends on are
     * pinned here instead: the tiles are identifiable, the numbers are drawn,
     * and the token is on the page.
     */
    public function test_the_grid_carries_what_the_drag_needs(): void
    {
        $this->upload(2);

        $page = $this->actingAs($this->staff)
            ->get(route('admin.product.edit', $this->product))
            ->assertOk();

        $html = $page->getContent();

        $this->assertStringContainsString('name="photos[]"', $html, 'The picker does not take several files.');
        $this->assertStringContainsString('multiple', $html);
        $this->assertStringContainsString('data-vp-shot=', $html, 'The tiles cannot be picked up.');
        $this->assertStringContainsString('۱', $html, 'The photographs are not numbered.');

        // The three buttons that were on every tile are gone — «جلوتر عقبتر
        // چیه اصلی کن چیه» — and nothing should quietly put them back.
        $this->assertStringNotContainsString('جلوتر', $html);
        $this->assertStringNotContainsString('عقب‌تر', $html);
        $this->assertStringNotContainsString('اصلی کن', $html);

        // The token the drag posts with. Without it the save is a 419 and the
        // grid on screen disagrees with the shop.
        $this->assertStringContainsString('name="csrf-token"', $html);
    }

    /** Somebody without the catalogue permission cannot rearrange a shop's shoes. */
    public function test_it_is_behind_the_catalogue_permission(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('admin.product.media.order', $this->product), ['order' => '1'])
            ->assertForbidden();
    }
}
