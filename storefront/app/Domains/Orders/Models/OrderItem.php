<?php

namespace App\Domains\Orders\Models;

use App\Domains\Franchise\Models\Franchise;
use App\Domains\Marketplace\Models\Settlement;
use App\Domains\Marketplace\Models\Vendor;
use App\Domains\Marketplace\Models\VendorOffer;
use App\Domains\Tenancy\Contracts\Tenant;
use App\Domains\Tenancy\TenantContext;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One line of one order, owned by one seller (§2's critical design rule).
 *
 * This model carries its own tenant scope rather than the shared
 * `BelongsToTenant` trait, because it is the one table that can belong to
 * either kind of seller: the column to filter on depends on the active
 * tenant's kind, and a platform line belongs to neither. The behaviour is the
 * same as the trait's otherwise — a tenant sees its own lines and nothing
 * else, and platform-side code runs with no tenant and sees everything.
 */
class OrderItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'quantity' => 'integer',
            'line_total' => 'integer',
            'commission_rate_bps' => 'integer',
            'commission_amount' => 'integer',
            'seller_payable' => 'integer',
            'refunded_amount' => 'integer',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            /** @var TenantContext $context */
            $context = app(TenantContext::class);

            if (! $context->hasTenant()) {
                return;
            }

            $tenant = $context->currentOrFail();
            $column = $tenant->tenantKind() === 'vendor' ? 'vendor_id' : 'franchise_id';

            $builder->where($builder->qualifyColumn($column), $tenant->tenantId());
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public function vendorOffer(): BelongsTo
    {
        return $this->belongsTo(VendorOffer::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(OrderItemRefund::class);
    }

    public function scopeForSeller(Builder $query, Tenant $tenant): Builder
    {
        $column = $tenant->tenantKind() === 'vendor' ? 'vendor_id' : 'franchise_id';

        return $query->where($query->qualifyColumn($column), $tenant->tenantId());
    }

    /**
     * Lines a payout run may pick up (§11: "When does vendor revenue become
     * eligible for settlement?"). Delivered, past the return hold, and not
     * already in a settlement.
     */
    public function scopeSettleable(Builder $query): Builder
    {
        $holdDays = (int) config('marketplace.settlement.hold_days_after_delivery');

        return $query->whereNull('settlement_id')
            ->where('seller_kind', 'vendor')
            ->whereIn('fulfillment_status', (array) config('marketplace.settlement.eligible_statuses'))
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '<=', now()->subDays($holdDays));
    }

    public function netPayable(): int
    {
        return $this->seller_payable - $this->refunds()->where('status', 'completed')->sum('amount');
    }

    public function isRefundable(): bool
    {
        return $this->refunded_amount < $this->line_total
            && ! in_array($this->fulfillment_status, ['cancelled'], true);
    }
}
