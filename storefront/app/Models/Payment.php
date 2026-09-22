<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to pay for one order.
 *
 * Not branch-scoped, and that is deliberate rather than an omission: the
 * gateway sends the customer back to a URL this application chose, and the
 * row is found by `authority` — unguessable either way — and then checked
 * against its own order, which **is** branch-scoped. Scoping this as well
 * would mean a callback that arrives with the wrong branch bound finds nothing
 * and the customer's money sits paid with the order unsettled, which is the
 * worst failure this whole flow has.
 *
 * **`authority` and `gateway_token` are two different things.** The first is
 * the key a returning customer is found by, and which side chose it depends on
 * the gateway: ZarinPal mints it, SnappPay is handed one. The second is the
 * instalment provider's own handle, which its verify and settle calls are made
 * with, and it is null on a card payment.
 */
class Payment extends Model
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    /** The customer came back from the gateway without paying. */
    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'order_id', 'gateway', 'authority', 'gateway_token', 'amount', 'status',
        'ref_id', 'card_pan', 'failure', 'paid_at', 'gateway_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'gateway_updated_at' => 'datetime',
            'amount' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The order's number, readable with no branch bound.
     *
     * Order is branch-scoped, so `$payment->order->number` is a fatal error on
     * any path where the tenant is not set — and the callback is exactly such
     * a path: the customer returns from the gateway and the branch is resolved
     * from the URL, which a franchise's callback carries and a bare one does
     * not. Losing a payment because the *error* handler crashed is the worst
     * version of this, so the number is read the way that always works.
     */
    public function orderNumber(): string
    {
        return (string) ($this->order()->withoutGlobalScopes()->first()?->number ?? $this->order_id);
    }

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }

    /**
     * **Which provider took the money, in words.**
     *
     * «وقتی پرداختی صورت میگیره مشخص نیست که این پرداخت با اسنپ پی بوده یا با
     * زرین پال» — and it was not: the panel printed `payment_status`
     * (paid/unpaid) and `orders.payment_method` (online/at-the-door), and the
     * gateway lived only in this column, which no screen read. Two providers
     * take money for this shop and the shop could not tell one from the other
     * on an order, which is also the one thing a reconciliation needs: a
     * زرین‌پال line and an اسنپ‌پی line arrive on different statements.
     *
     * **Its own map rather than the `Gateways` registry**, and that is the
     * point: a row naming a provider the shop has since disconnected still
     * has to be named here. `Gateways::named()` answers null for exactly that
     * case, by its own docblock, and an old order is not the place to find out
     * that a variable changed.
     *
     * Falls back to the token so a provider added later is visible rather than
     * blank — the same rule `Order::methodLabel()` follows.
     *
     * @return array<string, string>
     */
    public static function gatewayLabels(): array
    {
        return [
            'zarinpal' => 'زرین‌پال',
            'snapppay' => 'اسنپ‌پی (اقساطی)',
            // Not a gateway: «پول را گرفتم» on the order screen writes this,
            // and the money did not come through anybody's terminal.
            'panel' => 'ثبت دستی در پنل',
            'demo' => 'سفارش آزمایشی',
            'at-the-door' => 'بدون درگاه',
        ];
    }

    public function gatewayLabel(): string
    {
        return self::gatewayLabels()[$this->gateway] ?? (string) $this->gateway;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::PAID => 'پرداخت شد',
            self::FAILED => 'ناموفق',
            self::CANCELLED => 'لغو شد',
            default => 'در انتظار پرداخت',
        };
    }
}
