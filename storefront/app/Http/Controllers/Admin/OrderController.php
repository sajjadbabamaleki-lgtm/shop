<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Audit;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ShippingMethod;
use App\Support\Admin\DateRange;
use App\Support\Checkout\SettleOrder;
use App\Support\Fulfilment\FulfilmentSettings;
use App\Support\Fulfilment\Promise;
use App\Support\Search;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The branch's orders — §4 and §24.
 *
 * No branch condition anywhere in this file: Order is branch-scoped, so a
 * Shiraz manager quoting a Tehran order's number gets a 404 from the model
 * layer rather than a page. That is «changing an id in the URL must not work»,
 * enforced where it cannot be forgotten — including in the bulk action below,
 * where a list of ids arrives straight from a form and the scope is the only
 * thing standing between it and somebody else's shop.
 *
 * **What §4 asks for and is not here**, so that nobody has to read the whole
 * file to find out: print invoice and shipping label, refund, and the
 * return/exchange workflow. The first two are a print stylesheet and a view;
 * the last three need models this application does not have — there is no
 * refund and no return anywhere in the schema, and a button that pretended
 * otherwise would be worse than its absence. Filtering by seller and by
 * shipping method is missing for the same reason: the order line carries no
 * vendor and the order carries no shipping method.
 */
class OrderController extends Controller
{
    /** How many rows a page may hold — §24's «adjustable page size». */
    private const PAGE_SIZES = [20, 50, 100];

    public function index(Request $request): View
    {
        $range = $request->filled('range') ? DateRange::fromRequest($request) : null;

        $orders = $this->filtered($request, $range)
            ->paginate($this->perPage($request))
            ->withQueryString();

        return view('admin.orders', [
            'orders' => $orders,
            'statuses' => Order::statusLabels(),
            'range' => $range,
            'filters' => [
                'q' => (string) $request->query('q', ''),
                'status' => (string) $request->query('status', ''),
                'payment' => (string) $request->query('payment', ''),
                'sort' => $this->sort($request),
                'dir' => $this->direction($request),
                'per' => $this->perPage($request),
            ],
            'sizes' => self::PAGE_SIZES,
            // What a bulk action may move an order to. The transitions
            // themselves are still checked one order at a time below.
            'bulkTargets' => [
                Order::PAID => Order::statusLabels()[Order::PAID],
                Order::SHIPPED => Order::statusLabels()[Order::SHIPPED],
                Order::DELIVERED => Order::statusLabels()[Order::DELIVERED],
                Order::CANCELLED => Order::statusLabels()[Order::CANCELLED],
            ],
        ]);
    }

    /**
     * The list, filtered and sorted — one place, so the screen, the export and
     * «select everything that matches» cannot disagree about what the current
     * view actually is.
     *
     * @return Builder<Order>
     */
    private function filtered(Request $request, ?DateRange $range): Builder
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $payment = (string) $request->query('payment', '');

        return Order::query()
            ->when($q !== '', function (Builder $builder) use ($q): void {
                // Folded on both sides. Folding one and not the other fails
                // for exactly the rows somebody typed on a different keyboard,
                // and nobody can see why — see «Persian is typed three ways».
                $needle = '%'.fold_persian($q).'%';

                $builder->where(function (Builder $inner) use ($needle): void {
                    $inner->where(Search::fold('number'), 'ilike', $needle)
                        ->orWhere(Search::fold('contact_name'), 'ilike', $needle)
                        ->orWhere(Search::fold('contact_phone'), 'ilike', $needle)
                        ->orWhere(Search::fold('tracking_number'), 'ilike', $needle);
                });
            })
            ->when(array_key_exists($status, Order::statusLabels()), fn (Builder $b) => $b->where('status', $status))
            // Every value this column really holds. `refunded` was missing, so
            // an order whose money had been given back could not be filtered
            // for at all — see Order::paymentLabels().
            ->when(in_array($payment, array_keys(Order::paymentLabels()), true), fn (Builder $b) => $b->where('payment_status', $payment))
            ->when($range !== null, fn (Builder $b) => $b->whereBetween('placed_at', [$range->from, $range->to]))
            ->orderBy($this->sort($request), $this->direction($request));
    }

    /** Only columns that mean something to sort by, named rather than taken. */
    private function sort(Request $request): string
    {
        $sort = (string) $request->query('sort', 'placed_at');

        return in_array($sort, ['placed_at', 'grand_total', 'number'], true) ? $sort : 'placed_at';
    }

    private function direction(Request $request): string
    {
        return $request->query('dir') === 'asc' ? 'asc' : 'desc';
    }

    private function perPage(Request $request): int
    {
        $per = (int) $request->query('per', 20);

        return in_array($per, self::PAGE_SIZES, true) ? $per : 20;
    }

    public function show(Order $order, Promise $promise): View
    {
        $settings = FulfilmentSettings::for($order->branch);

        return view('admin.order', [
            'order' => $order->load('items', 'customer'),

            // §5's «Overdue» is derived, never stored: a stored flag needs
            // something to set it, and that something will not have run on the
            // morning somebody opens this screen.
            'overdue' => $promise->isOverdue($order),

            // §7's Mark-as-Shipped offers the branch's own methods.
            'methods' => ShippingMethod::where('branch_id', $order->branch_id)
                ->where('is_active', true)
                ->orderBy('id')
                ->get(),

            // §6's money, from the payments table rather than from the
            // order's own `payment_status`: that column says whether it is
            // paid, and this says by what and with which reference.
            'receipt' => $order->payments()->where('status', Payment::PAID)->latest('id')->first(),
            'attempts' => $order->payments()->latest('id')->get(),

            'delaysEnabled' => (bool) ($settings['delays_enabled'] ?? true),
            'maxDelay' => (int) ($settings['max_delay_days'] ?? 7),
            // §4's «complete timeline/history». The trail is append-only and
            // already written by the model on every change, so this is a read
            // rather than a second record that could disagree with the first.
            'trail' => Audit::where('auditable_type', Order::class)
                ->where('auditable_id', $order->id)
                ->with('actor')
                ->latest('id')
                ->limit(50)
                ->get(),
        ]);
    }

    /**
     * Move an order on.
     *
     * Paying and cancelling are not status edits — they move stock, so they go
     * through SettleOrder, which is the only thing besides the checkout that
     * touches a shelf. Shipping and delivering are just where the parcel is.
     */
    public function update(Request $request, Order $order, SettleOrder $settle): RedirectResponse
    {
        $to = $request->validate([
            'status' => ['required', Rule::in([Order::PAID, Order::SHIPPED, Order::DELIVERED, Order::CANCELLED])],
        ])['status'];

        $back = redirect()->route('admin.order', $order);

        if (! $this->allowed($order, $to)) {
            return $back->withErrors(['status' => "از «{$order->statusLabel()}» نمی‌شود به این وضعیت رفت."]);
        }

        $this->move($order, $to, $settle, $request->user()->name);

        return $back->with('status', 'وضعیت سفارش به‌روز شد.');
    }

    /**
     * The money arrived.
     *
     * §21's «پرداخت شد» used to be a bare status button: it moved the column
     * and recorded nothing else. For a shop whose orders are mostly paid at
     * the door that is the wrong shape — money arriving is an **event**, with
     * an hour and a method and sometimes a reference somebody will be asked
     * for later. A real checkout writes a `payments` row; the panel's own
     * button wrote none, so an order settled by staff had a thinner record
     * than one settled by a customer.
     *
     * `SettleOrder::paid` is still what sells the stock — this only puts the
     * receipt beside it, in the same transaction, so the two cannot disagree.
     */
    public function pay(Request $request, Order $order, SettleOrder $settle): RedirectResponse
    {
        $back = redirect()->route('admin.order', $order);

        // §18's duplicate click. Saying what is already true beats an error
        // and beats selling the same pair twice.
        if ($order->status === Order::PAID) {
            return $back->with('status', 'این سفارش از قبل پرداخت‌شده ثبت شده بود؛ چیزی تغییر نکرد.');
        }

        if (! $this->allowed($order, Order::PAID)) {
            return $back->withErrors(['status' => "سفارشی که «{$order->statusLabel()}» است را نمی‌شود پرداخت‌شده ثبت کرد."]);
        }

        $input = $request->validate([
            'method' => ['required', Rule::in(array_keys(Order::methodLabels()))],
            'reference' => ['nullable', 'string', 'max:60'],
        ]);

        // A `nullable` rule does not put the key in the validated array when
        // the field was not submitted, so reading it directly is a 500 for any
        // caller that omits it — the card always posts it, which is exactly
        // why nobody would have found this by clicking. Same remedy as
        // `FulfilmentController::ship`, which learned it first.
        $input += ['reference' => null];

        DB::transaction(function () use ($order, $input, $settle): void {
            $settle->paid($order);

            $order->forceFill(['payment_method' => $input['method']])->save();

            Payment::create([
                'order_id' => $order->id,
                // Not a gateway — this money did not come through one, and
                // saying «zarinpal» here would be a lie in the one table the
                // shop reconciles against.
                'gateway' => 'panel',
                'authority' => 'PANEL-'.Str::upper(Str::random(26)),
                'amount' => $order->grand_total,
                'status' => Payment::PAID,
                'ref_id' => $input['reference'] ?: null,
                'paid_at' => now(),
            ]);
        });

        return $back->with('status', 'پرداخت ثبت شد و موجودی رزروشده فروخته شد.');
    }

    /**
     * The order is off, and **why** is part of the record.
     *
     * `SettleOrder::cancelled` has always taken a reason and the panel has
     * always thrown it away, passing «Cancelled in the panel by …» instead —
     * so «چرا این لغو شد؟» had no answer a month later. The reason is required
     * here for that one purpose: it is the only part of a cancellation nobody
     * can reconstruct afterwards.
     */
    public function cancel(Request $request, Order $order, SettleOrder $settle): RedirectResponse
    {
        $back = redirect()->route('admin.order', $order);

        if ($order->status === Order::CANCELLED) {
            return $back->with('status', 'این سفارش از قبل لغو شده بود؛ چیزی تغییر نکرد.');
        }

        if (! $this->allowed($order, Order::CANCELLED)) {
            return $back->withErrors(['reason' => "سفارشی که «{$order->statusLabel()}» است را از اینجا نمی‌شود لغو کرد."]);
        }

        $input = $request->validate([
            'reason' => ['required', 'string', 'max:200'],
        ]);

        $settle->cancelled($order, $input['reason']);

        $order->forceFill(['staff_note' => trim($order->staff_note."\nعلت لغو: ".$input['reason'])])->save();

        return $back->with('status', 'سفارش لغو شد و موجودی رزروشده برگشت.');
    }

    /**
     * It reached the customer.
     *
     * The hour is editable and cannot be in the future, exactly as the
     * handover's is — a delivery that has not happened yet is not a delivery,
     * and §2's promise is judged against when it actually arrived.
     */
    public function deliver(Request $request, Order $order): RedirectResponse
    {
        $back = redirect()->route('admin.order', $order);

        if ($order->status === Order::DELIVERED) {
            return $back->with('status', 'این سفارش از قبل تحویل‌شده ثبت شده بود؛ چیزی تغییر نکرد.');
        }

        if (! $this->allowed($order, Order::DELIVERED)) {
            return $back->withErrors(['delivered_at' => "سفارشی که «{$order->statusLabel()}» است را نمی‌شود تحویل‌شده ثبت کرد."]);
        }

        $input = $request->validate([
            'delivered_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        $order->forceFill([
            'status' => Order::DELIVERED,
            'actual_delivered_at' => CarbonImmutable::parse($input['delivered_at'] ?? 'now'),
        ])->save();

        return $back->with('status', 'تحویل ثبت شد.');
    }

    /**
     * The tracking number and the shop's own note — §4's «Add/edit tracking
     * number» and «Internal notes».
     *
     * Separate from the status move on purpose: writing a tracking number is
     * not a change of state, and folding the two into one form is how somebody
     * correcting a typo in a code accidentally marks a parcel delivered.
     */
    public function annotate(Request $request, Order $order): RedirectResponse
    {
        $fields = $request->validate([
            'tracking_number' => ['nullable', 'string', 'max:60'],
            'staff_note' => ['nullable', 'string', 'max:2000'],
        ]);

        // `??`, not just `?:` — a `nullable` rule does not put the key in the
        // validated array when the field was never submitted at all, so
        // reading it directly is a 500 for any form that posts one of the two.
        $order->update([
            'tracking_number' => trim($fields['tracking_number'] ?? '') ?: null,
            'staff_note' => trim($fields['staff_note'] ?? '') ?: null,
        ]);

        return redirect()->route('admin.order', $order)->with('status', 'یادداشت و رهگیری ذخیره شد.');
    }

    /**
     * §24's «bulk actions». One target status for every order ticked.
     *
     * **Each order is still checked on its own.** A bulk move is a convenience,
     * not a way around the transition rules: an already-paid order in the
     * selection is skipped rather than paid twice, and the reply says how many
     * were skipped rather than reporting a clean sweep. Silence about the ones
     * that did not move is the failure mode this whole repository keeps being
     * bitten by.
     */
    public function bulk(Request $request, SettleOrder $settle): RedirectResponse
    {
        $input = $request->validate([
            'status' => ['required', Rule::in([Order::PAID, Order::SHIPPED, Order::DELIVERED, Order::CANCELLED])],
            'orders' => ['required', 'array', 'max:200'],
            'orders.*' => ['integer'],
        ]);

        // `whereIn` on a branch-scoped model: ids from another shop match
        // nothing rather than being moved by somebody who guessed them.
        $orders = Order::whereIn('id', $input['orders'])->get();

        $moved = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            if (! $this->allowed($order, $input['status'])) {
                $skipped++;

                continue;
            }

            $this->move($order, $input['status'], $settle, $request->user()->name);
            $moved++;
        }

        $said = fa_number($moved).' سفارش جابه‌جا شد.';

        if ($skipped > 0) {
            $said .= ' '.fa_number($skipped).' سفارش از وضعیت فعلی‌شان نمی‌توانستند به این وضعیت بروند و دست‌نخورده ماندند.';
        }

        return redirect()->back()->with('status', $said);
    }

    /**
     * §24's «export». The current view, not the whole table — a filtered
     * screen whose export ignores the filter is a spreadsheet nobody can trust.
     *
     * Streamed, so a hundred thousand orders do not become a hundred thousand
     * orders in memory. The BOM is what makes Excel open UTF-8 Persian as
     * Persian instead of as mojibake; without it this file is unreadable in
     * the one program most likely to open it.
     */
    public function export(Request $request): StreamedResponse
    {
        $range = $request->filled('range') ? DateRange::fromRequest($request) : null;
        $query = $this->filtered($request, $range);

        $name = 'orders-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['شماره', 'تاریخ', 'مشتری', 'تلفن', 'وضعیت', 'پرداخت', 'رهگیری', 'مبلغ']);

            $query->chunk(500, function ($orders) use ($out): void {
                foreach ($orders as $order) {
                    fputcsv($out, [
                        $order->number,
                        $order->placed_at?->format('Y-m-d H:i'),
                        $order->contact_name,
                        $order->contact_phone,
                        $order->statusLabel(),
                        $order->payment_status,
                        $order->tracking_number,
                        $order->grand_total,
                    ]);
                }
            });

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** The one place a status actually changes, so both routes agree. */
    private function move(Order $order, string $to, SettleOrder $settle, string $by): void
    {
        match ($to) {
            Order::PAID => $settle->paid($order),
            Order::CANCELLED => $settle->cancelled($order, "Cancelled in the panel by {$by}."),
            default => $order->update(['status' => $to]),
        };
    }

    /**
     * Which moves make sense from where.
     *
     * Written out rather than inferred, because "any status to any status" is
     * how an order that was already paid gets marked paid again and sells the
     * same stock twice.
     */
    private function allowed(Order $order, string $to): bool
    {
        return match ($order->status) {
            Order::PLACED => in_array($to, [Order::PAID, Order::CANCELLED], true),
            Order::PAID => in_array($to, [Order::SHIPPED, Order::CANCELLED], true),
            Order::SHIPPED => $to === Order::DELIVERED,
            default => false,
        };
    }
}
