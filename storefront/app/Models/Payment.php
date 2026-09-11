<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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

    /**
     * Which door this money came through, in words.
     *
     * On the screen beside the status, because the two answer different
     * questions and the panel was only ever showing one of them: «پرداخت شد»
     * on a row whose gateway is `panel` means somebody in this office said so,
     * and on a row whose gateway is `zarinpal` it means a bank did. Told apart
     * only by a column nobody could see, an order settled by hand and an order
     * paid by a customer read identically — and the shop finds out which it
     * was when it reconciles, or when the shoes have already gone.
     */
    public function gatewayLabel(): string
    {
        return match ($this->gateway) {
            'panel' => 'ثبت دستی در پنل',
            'zarinpal' => 'زرین‌پال',
            default => (string) $this->gateway,
        };
    }

    /**
     * The receipt for money the shop took some other way.
     *
     * Cash at the counter, a card-to-card transfer, an order the gateway never
     * confirmed and staff settled by hand. **Not a gateway** — saying
     * «zarinpal» here would be a lie in the one table the shop reconciles
     * against.
     *
     * It lives on the model because there are two doors into «paid» in the
     * panel — the order's own button and the grid's bulk action — and only the
     * first one wrote a row. `OrderController::move()` calls itself «the one
     * place a status actually changes, so both routes agree», and they did
     * agree about the status; they disagreed about the money. Eight orders
     * marked paid from the grid left no trace of *being* paid anywhere, so the
     * payments table did not add up to what the orders said, with nothing
     * going red.
     */
    public static function recordedInThePanel(Order $order, ?string $reference = null): self
    {
        return self::create([
            'order_id' => $order->id,
            'gateway' => 'panel',
            'authority' => 'PANEL-'.Str::upper(Str::random(26)),
            'amount' => $order->grand_total,
            'status' => self::PAID,
            'ref_id' => $reference ?: null,
            'paid_at' => now(),
        ]);
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
