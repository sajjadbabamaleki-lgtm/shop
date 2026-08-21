<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ShippingMethod;
use App\Support\Fulfilment\FulfilmentSettings;
use App\Support\Fulfilment\Promise;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * §7's primary admin actions: «I shipped the order» and «register a delay».
 *
 * Both are consequential writes, so both are idempotent in the way §18 asks
 * for — «Consequential write operations should be idempotent where practical to
 * prevent duplicate shipment/refund actions». A double-tapped Mark-as-Shipped
 * must not produce two handovers and two notifications.
 */
class FulfilmentController extends Controller
{
    /**
     * Action A — the parcel has actually been handed over.
     *
     * §7 is emphatic about when: «Use only after the parcel has actually been
     * handed to the carrier.» So this records an *event*, not an estimate, and
     * the deadline it was measured against is left exactly where it was.
     */
    public function ship(Request $request, Order $order, Promise $promise): RedirectResponse
    {
        $input = $request->validate([
            'carrier' => ['nullable', 'string', 'max:80'],
            'tracking_number' => ['nullable', 'string', 'max:60'],
            'shipping_method_id' => ['nullable', 'integer'],
            // Defaults to now and is editable, per §7. Not allowed in the
            // future: a handover that has not happened yet is not a handover,
            // and it would put the recalculated delivery window in fantasy.
            'shipped_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        // **Every optional key, present.** A `nullable` rule does not put the
        // key in the validated array when the field was not submitted, so
        // reading one directly is a 500 for any form that omits it — a form
        // this application posts from two different screens. Filling the gaps
        // once here beats a `??` on each of the four reads below, where the
        // fifth one added later would be the bug.
        $input += ['carrier' => null, 'tracking_number' => null, 'shipping_method_id' => null, 'shipped_at' => null];

        $back = redirect()->route('admin.order', $order);

        // §18: «Duplicate clicks on Mark as Shipped.» The second one is not an
        // error to shout about — it is somebody's thumb — so it says what is
        // already true and changes nothing.
        if ($order->actual_shipped_at) {
            return $back->with('status', 'این سفارش قبلاً ارسال‌شده ثبت شده بود؛ چیزی تغییر نکرد.');
        }

        if (! in_array($order->status, [Order::PAID, Order::PLACED], true)) {
            return $back->withErrors(['status' => "سفارشی که «{$order->statusLabel()}» است را نمی‌شود ارسال‌شده ثبت کرد."]);
        }

        $method = $this->method($order, $input['shipping_method_id']);
        $shippedAt = CarbonImmutable::parse($input['shipped_at'] ?? 'now');

        DB::transaction(function () use ($order, $input, $method, $shippedAt, $promise): void {
            $order->forceFill([
                'status' => Order::SHIPPED,
                'actual_shipped_at' => $shippedAt,
                'carrier' => $input['carrier'] ?: $method?->carrier,
                'tracking_number' => $input['tracking_number'] ?: $order->tracking_number,
                'shipping_method_id' => $method?->id ?? $order->shipping_method_id,
                // The parcel has gone; whatever it was late for, it is not
                // outstanding any more.
                'is_delayed' => false,
            ])->save();

            // §2: recalculate the delivery window from the actual handover.
            // The ship-by deadline is untouched — it is the record of what was
            // promised, and moving it would erase whether the shop kept it.
            $promise->recalculateFromShipment($order, $shippedAt);
        });

        return $back->with('status', 'ارسال ثبت شد و بازه تحویل از روز تحویل به پست دوباره حساب شد.');
    }

    /**
     * Action B — the shipping deadline moves, the lifecycle does not.
     *
     * §5: «'Delayed' should normally be a fulfillment condition/flag while the
     * order remains in Preparing or Ready to Ship.» So this sets a flag and
     * recomputes two dates; it does not invent a status.
     */
    public function delay(Request $request, Order $order, Promise $promise): RedirectResponse
    {
        $settings = FulfilmentSettings::for($order->branch);

        $back = redirect()->route('admin.order', $order);

        // §10 makes the delay workflow switchable, and §7 asks for «configurable
        // maximum-delay rules». Both are checked here rather than only hidden
        // in the view — a form somebody keeps open past a settings change is
        // still a form that posts.
        if (! ($settings['delays_enabled'] ?? true)) {
            return $back->withErrors(['days' => 'ثبت تأخیر برای این شعبه خاموش است.']);
        }

        if ($order->actual_shipped_at) {
            return $back->withErrors(['days' => 'این سفارش ارسال شده؛ تأخیر معنایی ندارد.']);
        }

        $max = (int) ($settings['max_delay_days'] ?? 7);

        try {
            $input = $request->validate([
                'days' => ['required', 'integer', 'min:1', 'max:'.max($max, 1)],
                'reason' => ['nullable', 'string', 'max:200'],
            ]);
        } catch (ValidationException $e) {
            return $back->withErrors([
                'days' => "تأخیر باید بین ۱ تا {$max} روز کاری باشد. سقف را در «پردازش و ارسال» عوض کنید.",
            ]);
        }

        $promise->delay($order, (int) $input['days'], $input['reason'] ?: null);

        return $back->with('status', 'تأخیر ثبت شد و مهلت ارسال و بازه تحویل دوباره حساب شدند.');
    }

    /** Confirm an order and make its promise — §1's clock starts here. */
    public function confirm(Order $order, Promise $promise): RedirectResponse
    {
        $back = redirect()->route('admin.order', $order);

        if ($order->confirmed_at) {
            return $back->with('status', 'این سفارش قبلاً تأیید شده بود؛ تاریخ‌ها همان‌اند.');
        }

        $promise->make($order, CarbonImmutable::now());

        return $back->with('status', 'سفارش تأیید شد و مهلت ارسال و بازه تحویل حساب شدند.');
    }

    private function method(Order $order, ?int $id): ?ShippingMethod
    {
        if ($id === null) {
            return $order->shippingMethod;
        }

        // Scoped to this branch on purpose: the id comes off a form, and a
        // method belonging to another shop is not this order's to carry.
        return ShippingMethod::where('branch_id', $order->branch_id)->find($id) ?? $order->shippingMethod;
    }
}
