<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to pay for one order.
 *
 * Not branch-scoped, and that is deliberate rather than an omission: the
 * gateway sends the customer back to a URL this application chose, and the
 * row is found by ZarinPal's `authority` — 36 unguessable characters — and
 * then checked against its own order, which **is** branch-scoped. Scoping this
 * as well would mean a callback that arrives with the wrong branch bound finds
 * nothing and the customer's money sits paid with the order unsettled, which
 * is the worst failure this whole flow has.
 */
class Payment extends Model
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    /** The customer came back from the gateway without paying. */
    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'order_id', 'gateway', 'authority', 'amount', 'status',
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
}
