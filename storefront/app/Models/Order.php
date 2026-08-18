<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\RecordsAudits;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An order at one branch.
 *
 * Branch-scoped like everything else a branch owns, so Shiraz's orders are
 * not reachable from Tehran by quoting a number or an id — §18 over the rows
 * that would hurt most to leak, since these carry a customer's name, phone
 * and address.
 *
 * Audited: §29 wants the changes worth explaining afterwards, and an order
 * moving between statuses, or being cancelled, is exactly that.
 */
class Order extends Model
{
    use BelongsToBranch, HasFactory, RecordsAudits;

    public const PLACED = 'placed';

    public const PAID = 'paid';

    public const SHIPPED = 'shipped';

    public const DELIVERED = 'delivered';

    public const CANCELLED = 'cancelled';

    /**
     * Statuses in which the branch is still holding stock for this order.
     *
     * Placed reserves; paid turns the reservation into a sale. Both of those
     * happen inside PlaceOrder and PayOrder, which are the only places stock
     * moves.
     */
    public const HOLDS_STOCK = [self::PLACED];

    protected $fillable = [
        'branch_id', 'customer_id', 'number', 'status',
        'subtotal', 'discount_total', 'shipping_total', 'grand_total',
        'payment_method', 'payment_status', 'tracking_number',
        'contact_name', 'contact_phone', 'province', 'city', 'address',
        'postal_code', 'note', 'staff_note', 'placed_at', 'paid_at', 'cancelled_at',
        // The fulfilment promise — §2 of the order-management addendum. An
        // estimate and an event are separate columns on purpose: sharing one
        // makes «when we said it would go» and «when it went» the same number,
        // and the shop can no longer tell a kept promise from a broken one.
        'confirmed_at', 'estimated_ship_by', 'actual_shipped_at',
        'estimated_delivery_from', 'estimated_delivery_to', 'actual_delivered_at',
        'is_delayed', 'delay_reason', 'carrier', 'shipping_method_id', 'promise_basis',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'estimated_ship_by' => 'date',
            'actual_shipped_at' => 'datetime',
            'estimated_delivery_from' => 'date',
            'estimated_delivery_to' => 'date',
            'actual_delivered_at' => 'datetime',
            'is_delayed' => 'boolean',
            'promise_basis' => 'array',
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'shipping_total' => 'integer',
            'grand_total' => 'integer',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Every attempt to pay for it — successful or not.
     *
     * A customer who abandons the gateway and comes back leaves two rows, and
     * both are kept: «چرا این سفارش دو بار پرداخت شد» has an answer only if
     * the failures are on the record too.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    /**
     * What a customer quotes on the phone.
     *
     * Not derived from the id: an id in a URL says how many orders the shop
     * has taken and makes the next one trivial to guess. Eight characters from
     * an alphabet with no 0/O or 1/I in it, because this gets read aloud.
     */
    public static function newNumber(): string
    {
        do {
            $number = 'VP-'.Str::upper(Str::password(8, symbols: false, numbers: true, letters: true, spaces: false));
            $number = str_replace(['O', '0', 'I', '1', 'L'], ['A', '2', 'B', '3', 'C'], $number);
        } while (self::acrossAllBranches()->where('number', $number)->exists());

        return $number;
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [self::PLACED], true);
    }

    public function holdsStock(): bool
    {
        return in_array($this->status, self::HOLDS_STOCK, true);
    }

    /**
     * The statuses, as a customer reads them.
     *
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::PLACED => 'ثبت شد',
            self::PAID => 'پرداخت شد',
            self::SHIPPED => 'ارسال شد',
            self::DELIVERED => 'تحویل شد',
            self::CANCELLED => 'لغو شد',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }
}
