<?php

namespace App\Models;

use App\Support\Payments\Gateways;
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
        'ref_id', 'card_pan', 'failure', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'amount' => 'integer'];
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

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::PAID => 'پرداخت شد',
            self::FAILED => 'ناموفق',
            self::CANCELLED => 'لغو شد',
            default => 'در انتظار پرداخت',
        };
    }

    /**
     * Which gateway this attempt went to, in words — «از طریق چه درگاهی».
     *
     * The two this shop has are named here; a gateway connected later is
     * named by its own driver's label, and a row whose driver has since been
     * disconnected falls back to the stored name, so an attempt is never
     * blank about where it went.
     */
    public function gatewayLabel(): string
    {
        return match ($this->gateway) {
            'zarinpal' => 'زرین‌پال (کارت بانکی)',
            'snapppay' => 'اسنپ‌پی (اقساطی)',
            'panel' => 'ثبت‌شده در پنل',
            default => app(Gateways::class)->named($this->gateway)?->label() ?? (string) $this->gateway,
        };
    }

    /**
     * What happened to this attempt, said the way the shop needs to hear it
     * when looking for a fault — «اگر سفارشی رو ثبت کرد و تا مرحله پرداخت رفت
     * ولی پرداختش نکرد … بفهمم مشکل از کجا بوده».
     *
     * Four different stories, and they point at different culprits: a refusal
     * is the gateway (or its settings), a cancel is the shopper, and an
     * attempt still pending after a while is somebody who reached the gateway
     * and never came back — a closed tab, a dropped connection, or a gateway
     * page that would not load.
     */
    public function outcomeLabel(): string
    {
        return match ($this->status) {
            self::PAID => 'پرداخت شد',
            self::CANCELLED => 'مشتری در درگاه انصراف داد',
            self::FAILED => 'درگاه نپذیرفت یا تأیید نشد',
            default => $this->created_at && $this->created_at->lt(now()->subMinutes(20))
                ? 'به درگاه رفت و برنگشت'
                : 'در حال پرداخت',
        };
    }

    /** The badge tone the panel uses for this outcome. */
    public function outcomeTone(): string
    {
        return match ($this->status) {
            self::PAID => 'delivered',
            self::FAILED, self::CANCELLED => 'cancelled',
            default => 'placed',
        };
    }
}
