<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\InventoryMovement;
use App\Models\Variant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Put the same number of pairs on every size the shop sells.
 *
 *   php artisan stock:add 10 --dry-run     # say what would move, write nothing
 *   php artisan stock:add 10 --force       # on the live shop
 *
 * «به کفش های موجود در فروشگاه برای هر کدام ده جفت اضافه کن» — and the unit is
 * the **size**, because that is the unit stock is stored in and the unit a
 * customer can buy. Ten pairs of a shoe with no size named is not a number this
 * shop can hold anywhere; splitting ten across five sizes would mean inventing
 * a split nobody chose.
 *
 * **This is the third thing in the application that writes `branch_inventory`,
 * and the only kind of third thing that may.** `PlaceOrder` reserves and
 * `SettleOrder` sells or releases — those two own the stock that belongs to
 * orders, and nothing else may touch it. Receiving goods is not that: it is
 * the same movement the panel's own count writes at `/admin/inventory`, and
 * this is that screen's `update()` in a loop, with its transaction, its
 * `lockForUpdate()` and its `inventory_movements` row kept exactly as they
 * are. A shelf that changes without a movement row is a shelf that cannot
 * explain itself later.
 *
 * Three things it deliberately does not do:
 *
 * - **It never opens a shelf that does not exist.** A size with an active
 *   price and no `branch_inventory` row has never been stocked here, and
 *   creating one is opening a shelf rather than adding to it — the panel makes
 *   the offer and the shelf together for exactly that reason. Those are
 *   counted and named in the output instead, because a silent skip is how a
 *   size stays at nought while everybody believes it was filled.
 * - **It never touches a branch it was not pointed at.** Stock is a branch's
 *   own property: a franchise did not receive this delivery. Central unless
 *   `--branch` says otherwise.
 * - **It leaves the test product alone.** «کالای آزمایشی، لطفاً نخرید» exists
 *   to be bought once with a real card and then removed; stocking it is the
 *   opposite of what it is for. `--everything` includes it, for whoever wants
 *   that.
 */
class AddStock extends Command
{
    use ConfirmableTrait;

    protected $signature = 'stock:add
        {units : how many pairs to add to every size}
        {--branch= : the branch slug; defaults to the central shop}
        {--everything : include the payment-gateway test product too}
        {--dry-run : print what would move and write nothing}
        {--force : run it on the live shop without asking}';

    protected $description = 'Add the same number of pairs to every size the shop sells';

    /** What a movement row says it was, for whoever reads the shelf's history. */
    private const NOTE = 'موجودی جدید به انبار اضافه شد.';

    public function handle(): int
    {
        $units = (int) $this->argument('units');

        if ($units < 1) {
            $this->error('Give it a number of pairs to add: php artisan stock:add 10');

            return self::FAILURE;
        }

        $branch = $this->option('branch')
            ? Branch::where('slug', $this->option('branch'))->first()
            : Branch::central();

        if (! $branch) {
            $this->error('There is no branch with that slug.');

            return self::FAILURE;
        }

        // The branch has to be bound before anything reads a price or a shelf:
        // both are branch-scoped and fail closed, so an unbound query here
        // would report a shop that sells nothing and add stock to none of it.
        return app(TenantContext::class)->forBranch($branch, function () use ($branch, $units) {
            $sizes = $this->sizesTheShopSells();

            if ($sizes->isEmpty()) {
                $this->warn('This shop lists nothing, so there is no shelf to add to.');

                return self::SUCCESS;
            }

            $shelved = $sizes->filter(fn (Variant $v) => $v->stock !== null);
            $unshelved = $sizes->reject(fn (Variant $v) => $v->stock !== null);

            $this->line('');
            $this->line("Branch:   {$branch->name} ({$branch->slug})");
            $this->line('Products: '.$sizes->pluck('product_id')->unique()->count());
            $this->line('Sizes:    '.$shelved->count().' on the shelf, adding '.$units.' pairs to each');
            $this->line('Total:    '.($shelved->count() * $units).' pairs');

            if ($unshelved->isNotEmpty()) {
                $this->line('');
                $this->warn($unshelved->count().' size(s) are priced here but have never been stocked, so there is');
                $this->warn('no shelf to add to. Open them in /admin/inventory first:');

                foreach ($unshelved->take(20) as $variant) {
                    $this->line('  - '.$variant->product?->title.' — '.$variant->size_value.' — '.$variant->display_color);
                }

                if ($unshelved->count() > 20) {
                    $this->line('  … and '.($unshelved->count() - 20).' more.');
                }
            }

            $this->line('');

            if ($this->option('dry-run')) {
                $this->info('Nothing was written: this was a dry run.');

                return self::SUCCESS;
            }

            if (! $this->confirmToProceed('این دستور موجودی فروشگاه واقعی را تغییر می‌دهد.')) {
                return self::FAILURE;
            }

            $moved = $this->add($shelved, $branch, $units);

            $this->info("Done. {$moved} size(s) restocked, {$units} pairs each.");

            return self::SUCCESS;
        });
    }

    /**
     * Every size of every shoe this branch is really selling.
     *
     * The same three conditions `Product::listable()` asks — active,
     * published, and priced here — because «کفش‌های موجود در فروشگاه» is the
     * shop's own listing and not the whole `products` table. A retired shoe is
     * out by the first of them, which is what keeps the five setup shoes and
     * anything archived in the panel off this.
     *
     * @return Collection<int, Variant>
     */
    private function sizesTheShopSells()
    {
        return Variant::query()
            ->where('variants.status', 'active')
            ->whereHas('offer', fn (Builder $offer) => $offer->where('status', 'active'))
            ->whereHas('product', function (Builder $product) {
                $product->where('status', 'active')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());

                if (! $this->option('everything')) {
                    $product->where('slug', '!=', MakeDemoProduct::SLUG);
                }
            })
            ->with(['stock', 'product'])
            ->get();
    }

    /**
     * The panel's own count, run once per size.
     *
     * Read under `lockForUpdate()` inside the transaction and added to rather
     * than set: between the list being gathered above and this line, somebody
     * may have bought a pair. Setting a number worked out beforehand would
     * quietly undo their order; adding to what the row actually holds cannot.
     *
     * @param  Collection<int, Variant>  $sizes
     */
    private function add($sizes, Branch $branch, int $units): int
    {
        $moved = 0;

        foreach ($sizes as $variant) {
            DB::transaction(function () use ($variant, $branch, $units, &$moved) {
                $shelf = BranchInventory::whereKey($variant->stock->id)->lockForUpdate()->first();

                if (! $shelf) {
                    return;
                }

                // `sellable_stock` is a generated column — `stock_on_hand`
                // minus `stock_reserved`, worked out by Postgres — so it is
                // never written here. Writing it throws, and a migration that
                // throws stops the shop from starting.
                $shelf->stock_on_hand += $units;
                $shelf->save();

                InventoryMovement::create([
                    'branch_id' => $branch->id,
                    'variant_id' => $variant->id,
                    'type' => 'adjustment',
                    'quantity' => $units,
                    'note' => self::NOTE,
                ]);

                $moved++;
            });
        }

        return $moved;
    }
}
