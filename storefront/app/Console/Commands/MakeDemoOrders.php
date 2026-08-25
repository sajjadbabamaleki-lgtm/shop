<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Variant;
use App\Support\Checkout\PlaceOrder;
use App\Support\Checkout\SettleOrder;
use App\Support\Fulfilment\Promise;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * A shelf of pretend orders, so the panel can be seen doing its job.
 *
 *   php artisan demo:orders            # make them
 *   php artisan demo:orders --remove   # take them away again
 *
 * «چند نمونه هم سفارش نمونه فیک بساز که مثلا ثبت شده، که اصلا بدونیم در حالت
 * های مختلف پنل چجوری کار میکنه.» Every screen in `/admin` is a view of orders
 * — the dashboard's six figures, the grid's filters, the fulfilment actions,
 * the promise that goes red when it is missed — and with an empty shop all of
 * them read zero. Nothing in the panel can be judged against zero.
 *
 * **They are made the way a customer would make them.** A basket, then
 * `PlaceOrder`, then `SettleOrder` and `Promise` — the same classes checkout
 * and the panel use. Nothing is inserted straight into the table, because an
 * order assembled by hand has no stock movement behind it, no reservation, no
 * `inventory_movements` row and no payment: the order screen would look right
 * and every screen that counts something would disagree with it. Going through
 * the real machinery costs nothing here and means the demo shop is *consistent*
 * — the stock the panel shows is the stock these orders actually took.
 *
 * **They say what they are.** Every one carries `staff_note` = DEMO_NOTE and a
 * customer whose telephone is in a reserved test range (0999…), so `--remove`
 * can find them again exactly and so nobody reads one as a real sale. The
 * removal goes back through `SettleOrder::cancelled` first, which puts the
 * stock back on the shelf before the rows are deleted — deleting them outright
 * would leave the units reserved for orders that no longer exist.
 *
 * It is not wired into any seeder or into `liara_pre_start.sh`, and it never
 * should be: a deploy that quietly invented eight sales would put numbers on
 * the dashboard that nobody sold.
 */
class MakeDemoOrders extends Command
{
    protected $signature = 'demo:orders
        {--branch= : the branch slug to make them at; defaults to the central shop}
        {--floor=1 : units of each size the demo will not touch, so the shop keeps selling}
        {--lend : put the units the demo needs onto the shelf first, and take them back on --remove}
        {--remove : delete the demo orders instead of making them}';

    protected $description = 'Make (or remove) sample orders so the panel can be seen in every state';

    /** Stamped on `staff_note`, and the only thing `--remove` trusts. */
    public const NOTE = 'DEMO — سفارش نمونه برای آزمایش پنل';

    /**
     * The note on a lent unit, and the only thing that says which units to
     * take back off the shelf again. It lives in `inventory_movements`, which
     * is already the ledger that explains a branch's stock — so a borrowed
     * unit is not a secret, it is a line somebody can read.
     */
    public const LENT = 'DEMO — موجودی قرضی برای سفارش‌های نمونه';

    /**
     * A reserved prefix, so a demo customer can never collide with a real one.
     * 0999 is not allocated to any Iranian operator.
     */
    public const PHONE_PREFIX = '0999';

    public function handle(TenantContext $tenant, PlaceOrder $place, SettleOrder $settle, Promise $promise): int
    {
        $branch = $this->option('branch')
            ? Branch::where('slug', $this->option('branch'))->first()
            : Branch::central();

        if (! $branch) {
            $this->error('آن شعبه پیدا نشد.');

            return self::FAILURE;
        }

        return $tenant->forBranch($branch, function () use ($branch, $place, $settle, $promise) {
            return $this->option('remove')
                ? $this->remove($branch, $settle)
                : $this->make($branch, $place, $settle, $promise);
        });
    }

    /**
     * Eight orders, chosen to cover what the panel can show rather than to be
     * eight of anything.
     *
     * Two are «ثبت شد» and unconfirmed, which is the state the dashboard's
     * «نیاز به رسیدگی» counts and the one a shop actually acts on. One is
     * confirmed and waiting, one is late — its ship-by deadline is in the past,
     * which is the only way to see the promise go red. One is on its way with a
     * tracking number, one arrived, one was cancelled before payment (so its
     * stock went back) and one was refunded after it (so its sale was
     * reversed). Every branch of `SettleOrder` and of the fulfilment screens is
     * represented once.
     */
    private function make(Branch $branch, PlaceOrder $place, SettleOrder $settle, Promise $promise): int
    {
        if ($this->existing($branch)->exists()) {
            $this->warn('سفارش‌های نمونه از قبل هستند. اول با --remove پاکشان کنید.');

            return self::FAILURE;
        }

        $variants = $this->sellable($branch);

        if ($variants->isEmpty()) {
            $this->error('این شعبه هیچ سایز قابل فروشی ندارد که بشود سفارشی ساخت.');

            return self::FAILURE;
        }

        // What is on the shelf, counted down as the demo takes from it. The
        // orders below are placed for real, so asking for a unit that is not
        // there is a `CannotFulfil` halfway through a set — half a demo and
        // half a stack trace. Tracking it here means the plan bends to the
        // shop instead.
        // **A floor the demo will not go below.** These orders take real stock,
        // and a size the demo empties stops being sellable — which on a thin
        // shop takes the product out of «پرفروش‌ترین‌ها» and can shorten the
        // front page itself. Found by `check-parity.js`: on a fixture with one
        // of each size, eight demo orders cut the home page from 4834px to
        // 3029px, because the bands had nothing left to show.
        //
        // So a size lends the demo everything above `--floor` and not one unit
        // more. The default of 1 means the shop looks exactly as it did to a
        // customer, whatever the panel is showing.
        $floor = max(0, (int) $this->option('floor'));
        $left = [];

        foreach ($variants as $variant) {
            $left[$variant->id] = max(0, $variant->sellableStock() - $floor);
        }

        // **`--lend`: the demo brings its own stock.**
        //
        // A shop that holds one of each size — which is what a freshly seeded
        // one holds, and what the live shop turned out to hold — has nothing
        // above the floor to lend, so the guard above stops. The obvious way
        // out is `--floor=0`, and it is the wrong one: measured on exactly
        // that shop, eight demo orders emptied **all eight** sizes and the
        // home page collapsed from 4834px to 3029px. The panel would fill up
        // by taking the shop down.
        //
        // So the units are put on the shelf first and taken off again by
        // `--remove`. Sellable stock is unchanged throughout — every borrowed
        // unit is immediately spoken for by the order that borrowed it — and
        // each one is a row in `inventory_movements` carrying `self::LENT`,
        // which is both the audit trail and how `--remove` knows what to
        // withdraw. Nothing is invented that is not written down.
        if ($this->option('lend')) {
            // Two per size covers the plan's worst case — eight orders, one of
            // which asks for two of a line — with room over, and it is small
            // enough that the borrowed stock is obviously a loan rather than a
            // restock.
            $lent = 0;

            foreach ($variants as $variant) {
                $this->lend($branch, $variant, 2);
                $left[$variant->id] += 2;
                $lent += 2;
            }

            $this->line(fa_number($lent).' واحد موجودی قرض گرفته شد تا از سهم فروش واقعی برداشته نشود؛ با --remove پس داده می‌شود.');
        }

        if (array_sum($left) < 1) {
            $this->error('موجودی این شعبه فقط به اندازه فروش واقعی است؛ برای ساختن نمونه چیزی نمی‌ماند.');
            $this->line('با --lend اجرا کنید: موجودی لازم را خودش قرض می‌گیرد و با --remove پس می‌دهد.');
            $this->line('(--floor=0 این کار را نکنید؛ روی مغازه‌ای با یک دانه از هر سایز، سایت را خالی می‌کند.)');

            return self::FAILURE;
        }

        // Names and cities that read like a customer list rather than like
        // «تست ۱». A screen full of placeholder text cannot be judged either.
        $people = [
            ['مریم رضایی', 'تهران', 'تهران', 'خیابان ولیعصر، کوچه بهار، پلاک ۱۲، واحد ۳'],
            ['علی موسوی', 'اصفهان', 'اصفهان', 'خیابان چهارباغ بالا، مجتمع نگین، طبقه ۴'],
            ['زهرا محمدی', 'فارس', 'شیراز', 'بلوار زند، نبش کوچه ۷، ساختمان آرام'],
            ['حسین کریمی', 'خراسان رضوی', 'مشهد', 'بلوار وکیل‌آباد، خیابان لادن، پلاک ۴۵'],
            ['نگار احمدی', 'البرز', 'کرج', 'مهرشهر، بلوار ارم، کوچه ۹، پلاک ۲۱'],
            ['رضا صادقی', 'آذربایجان شرقی', 'تبریز', 'خیابان آبرسان، کوی گلها، پلاک ۸'],
            ['سارا نوری', 'گیلان', 'رشت', 'خیابان مطهری، روبروی بانک ملی، پلاک ۳۳'],
            ['امیر حسینی', 'تهران', 'ری', 'خیابان فداییان اسلام، کوچه شهید رضایی، پلاک ۵'],
        ];

        $now = CarbonImmutable::now();

        // Spread across the last three weeks, so the dashboard's ranges and the
        // grid's date filters have something to separate. Newest first in this
        // list; `placed_at` is moved after placing, because PlaceOrder rightly
        // stamps «now».
        // **The two cancelled ones are made first, and that is not cosmetic.**
        // They hand their units straight back, so making them early lends the
        // same stock to the six that keep theirs. Made last, on a branch with
        // a unit or two per size, they are the two that get skipped — and they
        // are exactly the two states somebody opens the panel to look at.
        // `ago` is what puts each one in its right place in the day book; this
        // order is only the order they are made in.
        $plan = [
            ['state' => 'cancelled', 'ago' => 4,  'label' => 'لغو شد، پیش از پرداخت'],
            ['state' => 'refunded',  'ago' => 17, 'label' => 'لغو شد، بعد از پرداخت برگشت خورد'],
            ['state' => 'placed',    'ago' => 0,  'label' => 'ثبت شد، تأیید نشده'],
            ['state' => 'placed',    'ago' => 1,  'label' => 'ثبت شد، تأیید نشده'],
            ['state' => 'confirmed', 'ago' => 2,  'label' => 'پرداخت شد، در حال آماده‌سازی'],
            ['state' => 'late',      'ago' => 9,  'label' => 'پرداخت شد، از مهلت ارسال گذشته'],
            ['state' => 'shipped',   'ago' => 6,  'label' => 'ارسال شد'],
            ['state' => 'delivered', 'ago' => 14, 'label' => 'تحویل شد'],
        ];

        $made = [];
        $short = [];

        foreach ($plan as $i => $step) {
            $person = $people[$i % count($people)];
            $placedAt = $now->subDays($step['ago'])->setTime(10 + ($i % 8), ($i * 7) % 60);

            // Two-line orders are a nicety; all eight states are the point.
            // So a second line is only asked for while the shelf has more
            // units left than there are orders still to place.
            $roomy = array_sum($left) > (count($plan) - $i);

            try {
                $order = $this->place($branch, $place, $variants, $left, $person, $i, $roomy);
            } catch (Throwable $e) {
                $this->error("سفارش نمونه ساخته نشد: {$e->getMessage()}");

                return self::FAILURE;
            }

            if (! $order) {
                $short[] = $step['label'];

                continue;
            }

            $order->forceFill(['placed_at' => $placedAt, 'staff_note' => self::NOTE])->save();

            $this->advance($order, $step['state'], $placedAt, $settle, $promise);

            // A cancelled or refunded order puts its units back, and the next
            // order in the plan may have them.
            if (in_array($step['state'], ['cancelled', 'refunded'], true)) {
                foreach ($order->items as $item) {
                    if (array_key_exists($item->variant_id, $left)) {
                        $left[$item->variant_id] += $item->quantity;
                    }
                }
            }

            // `toman()`, not the raw column: totals are stored in Rial and the
            // shop reads prices in Toman, so printing the integer here made
            // every demo order look ten times its own price on the panel.
            $made[] = [$order->refresh()->number, $step['label'], toman($order->grand_total).' تومان'];
        }

        if ($made === []) {
            $this->error('موجودی این شعبه برای حتی یک سفارش نمونه کافی نبود.');

            return self::FAILURE;
        }

        $this->table(['شماره', 'وضعیت', 'مبلغ'], $made);
        $this->info(count($made).' سفارش نمونه در شعبه «'.$branch->name.'» ساخته شد.');

        // Said out loud rather than silently skipped: a state the panel cannot
        // be seen in is exactly what somebody would go looking for.
        if ($short !== []) {
            $this->warn('این حالت‌ها ساخته نشدند چون موجودی نرسید: '.implode('، ', $short));
            $this->line('برای دیدنشان موجودی شعبه را بالا ببرید و دوباره اجرا کنید.');
        }
        $this->line('برای پاک کردنشان: php artisan demo:orders --remove'.($this->option('branch') ? ' --branch='.$branch->slug : ''));

        return self::SUCCESS;
    }

    /**
     * One order, placed the way the shop places one.
     *
     * The basket is a real `Cart` with real lines, so `PlaceOrder` locks the
     * shelf, writes the reservation and the movement, and prices the lines off
     * the branch's own offers — the demo order's total is a number this branch
     * would really have charged.
     */
    private function place(Branch $branch, PlaceOrder $place, $variants, array &$left, array $person, int $i, bool $roomy): ?Order
    {
        // Two lines on every third order, so the order screen is seen holding
        // one line and holding several — but only on a shop with the units to
        // spare. Every want here bends to the shelf.
        $wanted = ($i % 3 === 0 && $roomy) ? 2 : 1;
        $chosen = [];

        for ($n = 0, $at = $i; $n < $wanted && count($chosen) < $wanted; $n++) {
            for ($tried = 0; $tried < $variants->count(); $tried++, $at++) {
                $variant = $variants[$at % $variants->count()];

                if (isset($chosen[$variant->id]) || ($left[$variant->id] ?? 0) < 1) {
                    continue;
                }

                // Two of something on the first line when the shelf can spare
                // it, so a quantity other than 1 appears somewhere.
                $quantity = ($n === 0 && $i % 2 === 1 && $roomy && $left[$variant->id] >= 2) ? 2 : 1;

                $chosen[$variant->id] = $quantity;
                $left[$variant->id] -= $quantity;
                $at++;

                break;
            }
        }

        if ($chosen === []) {
            return null;
        }

        $cart = Cart::create(['branch_id' => $branch->id, 'token' => 'demo-'.uniqid()]);

        foreach ($chosen as $variantId => $quantity) {
            $cart->items()->create(['variant_id' => $variantId, 'quantity' => $quantity]);
        }

        return $place($cart->load('items'), [
            'name' => $person[0],
            'phone' => self::PHONE_PREFIX.str_pad((string) (1000000 + $i), 7, '0', STR_PAD_LEFT),
            'province' => $person[1],
            'city' => $person[2],
            'address' => $person[3],
            'postal_code' => '1'.str_pad((string) (1000000000 + $i), 9, '0', STR_PAD_LEFT),
            'note' => $i % 4 === 0 ? 'لطفاً قبل از ارسال تماس بگیرید.' : null,
        ]);
    }

    /**
     * Walk one order to where it is supposed to end up.
     *
     * Every move goes through the class the panel's own buttons call, so a
     * demo order is in a state the application can really produce. The one
     * thing written directly is the *timing* — a promise made three weeks ago
     * cannot be made by calling `Promise::make()` today without lying about
     * when the clock started.
     */
    private function advance(Order $order, string $state, CarbonImmutable $placedAt, SettleOrder $settle, Promise $promise): void
    {
        if ($state === 'placed') {
            return;
        }

        if ($state === 'cancelled') {
            $settle->cancelled($order, 'مشتری منصرف شد');
            $order->forceFill(['cancelled_at' => $placedAt->addHours(3)])->save();

            return;
        }

        // Everything below here was paid for.
        $settle->paid($order);
        $paidAt = $placedAt->addMinutes(11);
        $order->forceFill(['paid_at' => $paidAt])->save();

        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'demo',
            'authority' => 'DEMO'.str_pad((string) $order->id, 32, '0', STR_PAD_LEFT),
            'amount' => $order->grand_total,
            'status' => Payment::PAID,
            'ref_id' => (string) (700000000 + $order->id),
            'card_pan' => '621986******'.str_pad((string) (1000 + $order->id), 4, '0', STR_PAD_LEFT),
            'paid_at' => $paidAt,
        ]);

        if ($state === 'refunded') {
            $settle->cancelled($order, 'مرجوعی مشتری');
            $order->forceFill(['cancelled_at' => $paidAt->addDays(2)])->save();

            return;
        }

        // §1: the clock starts at confirmation. Made *as of* the day the order
        // was placed, which is what puts the late one's deadline in the past.
        $promise->make($order, $paidAt);
        $order->refresh();

        if ($state === 'confirmed') {
            return;
        }

        if ($state === 'late') {
            // Not a status — §5 is explicit that a delay is a flag on an order
            // still being prepared. This is what makes one row in the grid red
            // and gives the dashboard's «تأخیر» something to count.
            $order->forceFill([
                'is_delayed' => true,
                'delay_reason' => 'تأمین سایز از انبار مرکزی طول کشید',
            ])->save();

            return;
        }

        $shippedAt = $paidAt->addDays(2);
        $promise->recalculateFromShipment($order, $shippedAt);

        $order->forceFill([
            'status' => Order::SHIPPED,
            'actual_shipped_at' => $shippedAt,
            'carrier' => 'پست پیشتاز',
            // Latin digits: the field is `dir="ltr"` and a real carrier's
            // number is what gets pasted into a tracking site.
            'tracking_number' => str_pad((string) (200000000000 + $order->id), 13, '0', STR_PAD_LEFT),
            'is_delayed' => false,
        ])->save();

        if ($state === 'delivered') {
            $order->forceFill([
                'status' => Order::DELIVERED,
                'actual_delivered_at' => $shippedAt->addDays(3),
            ])->save();
        }
    }

    /**
     * Take them away, stock and all.
     *
     * `SettleOrder::cancelled` first, so anything still reserved or sold goes
     * back on the shelf; deleting the rows alone would leave the branch short
     * by however many units the demo held, and nothing would say why.
     */
    private function remove(Branch $branch, SettleOrder $settle): int
    {
        $orders = $this->existing($branch)->get();

        if ($orders->isEmpty()) {
            // Still worth reclaiming: if the orders went some other way, the
            // lent units would otherwise sit on the shelf for good with only a
            // ledger line to explain them.
            $stray = $this->reclaim($branch);

            $this->info('سفارش نمونه‌ای برای پاک کردن نبود.');

            if ($stray > 0) {
                $this->line(fa_number($stray).' واحد موجودی قرضی از قفسه برداشته شد.');
            }

            return self::SUCCESS;
        }

        foreach ($orders as $order) {
            if ($order->status === Order::CANCELLED) {
                continue;
            }

            // **A shipped or delivered order is walked back to «paid» first.**
            // `SettleOrder::cancelled` reverses a sale only for an order that
            // is PAID, and releases a reservation only for one that is holding
            // stock — a SHIPPED or DELIVERED order is neither, and rightly so:
            // in a real shop those units are on somebody's doorstep and are not
            // coming back by cancelling a record.
            //
            // A demo parcel is not on anybody's doorstep. Left alone, every
            // run of this command would take those units off the shelf for
            // good, and a shop that made and cleared the demo three times
            // would be six units short with nothing anywhere saying why. Found
            // by `DemoOrdersTest::test_removing_them_puts_the_stock_back`,
            // which counted the shelf before and after.
            if (in_array($order->status, [Order::SHIPPED, Order::DELIVERED], true)) {
                $order->forceFill(['status' => Order::PAID])->save();
            }

            $settle->cancelled($order, 'پاک کردن سفارش‌های نمونه');
        }

        $ids = $orders->pluck('id');

        DB::transaction(function () use ($ids) {
            Payment::whereIn('order_id', $ids)->delete();
            Order::whereIn('id', $ids)->delete();

            // The pretend customers too, but only ones nothing else points at.
            // `Customer` has no `orders()` relation — a customer belongs to the
            // shop and an order belongs to a branch — so the check is a plain
            // subquery, across every branch, because a demo made at one branch
            // must not be deleted from under another.
            Customer::where('phone', 'like', self::PHONE_PREFIX.'%')
                ->whereNotIn('id', Order::acrossAllBranches()
                    ->whereNotNull('customer_id')
                    ->select('customer_id'))
                ->delete();
        });

        $reclaimed = $this->reclaim($branch);

        $this->info($orders->count().' سفارش نمونه پاک شد و موجودی‌شان برگشت.');

        if ($reclaimed > 0) {
            $this->line(fa_number($reclaimed).' واحد موجودی قرضی هم از قفسه برداشته شد.');
        }

        return self::SUCCESS;
    }

    /**
     * Put `$units` of one size on the shelf, and write down that they are a
     * loan.
     *
     * The movement is what makes this reversible and what makes it honest: the
     * stock ledger already exists to explain a branch's stock, and a unit that
     * appeared without a line in it would be exactly the kind of number nobody
     * can account for later.
     */
    private function lend(Branch $branch, Variant $variant, int $units): void
    {
        DB::transaction(function () use ($branch, $variant, $units): void {
            $shelf = BranchInventory::where('branch_id', $branch->id)
                ->where('variant_id', $variant->id)
                ->lockForUpdate()
                ->first();

            if (! $shelf) {
                return;
            }

            $shelf->stock_on_hand += $units;
            $shelf->save();

            InventoryMovement::create([
                'branch_id' => $branch->id,
                'variant_id' => $variant->id,
                'type' => 'adjustment',
                'quantity' => $units,
                'note' => self::LENT,
            ]);
        });
    }

    /**
     * Take the loan back.
     *
     * Read off the ledger rather than recomputed, so it withdraws exactly what
     * it put in however many times the demo was made and cleared. Runs *after*
     * the orders have released their stock, or it would be trying to withdraw
     * units the orders are still holding.
     *
     * A size that has genuinely sold since is not driven negative — the shelf
     * gives back what it can and the rest is dropped, because a demo tidying
     * itself up must never invent a shortage.
     */
    private function reclaim(Branch $branch): int
    {
        $lent = InventoryMovement::where('branch_id', $branch->id)
            ->where('note', self::LENT)
            ->get()
            ->groupBy('variant_id')
            ->map(fn ($rows) => (int) $rows->sum('quantity'));

        $taken = 0;

        foreach ($lent as $variantId => $units) {
            DB::transaction(function () use ($branch, $variantId, $units, &$taken): void {
                $shelf = BranchInventory::where('branch_id', $branch->id)
                    ->where('variant_id', $variantId)
                    ->lockForUpdate()
                    ->first();

                if (! $shelf) {
                    return;
                }

                $back = min($units, max(0, $shelf->sellable_stock));

                if ($back < 1) {
                    return;
                }

                $shelf->stock_on_hand -= $back;
                $shelf->save();

                InventoryMovement::create([
                    'branch_id' => $branch->id,
                    'variant_id' => $variantId,
                    'type' => 'adjustment',
                    'quantity' => -$back,
                    'note' => 'DEMO — پس دادن موجودی قرضی',
                ]);

                $taken += $back;
            });
        }

        InventoryMovement::where('branch_id', $branch->id)->where('note', self::LENT)->delete();

        return $taken;
    }

    /** The demo orders at this branch, found by the note they carry. */
    private function existing(Branch $branch)
    {
        return Order::where('branch_id', $branch->id)->where('staff_note', self::NOTE);
    }

    /**
     * Variants this branch can actually supply, fullest shelf first.
     *
     * `sellable()` is the application's own definition of «can go in a
     * basket» — live SKU, this branch lists it, this branch has one — so the
     * demo can never place an order the shop itself would have refused.
     *
     * No minimum beyond one unit, and the fullest first. A shop mid-season has
     * single units left in half its sizes; demanding three would have said
     * «not enough stock» on a branch with plenty, and taking from the emptiest
     * shelf would empty a size somebody is really selling.
     *
     * @return Collection<int, Variant>
     */
    private function sellable(Branch $branch)
    {
        return Variant::query()
            ->sellable()
            ->with(['product', 'stock'])
            ->get()
            ->sortByDesc(fn (Variant $variant) => $variant->sellableStock())
            ->take(12)
            ->values();
    }
}
