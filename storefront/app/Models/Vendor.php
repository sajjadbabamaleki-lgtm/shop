<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A company selling on this platform.
 *
 * Not branch-scoped, and that absence is the design: §1 says a Vendor and a
 * Franchise are different things, and a vendor with a branch_id would be a
 * shop of ours rather than somebody else's business.
 *
 * Audited, because approving, suspending and rejecting a vendor are exactly
 * the decisions §29 wants explainable afterwards.
 */
class Vendor extends Model
{
    use HasFactory, RecordsAudits;

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const SUSPENDED = 'suspended';

    public const REJECTED = 'rejected';

    protected $fillable = [
        'slug', 'name', 'legal_name', 'national_id', 'phone', 'email', 'address',
        'status', 'status_note', 'approved_at', 'iban', 'account_holder',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function offers(): HasMany
    {
        return $this->hasMany(VendorOffer::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(VendorUser::class);
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isApproved(): bool
    {
        return $this->status === self::APPROVED;
    }

    /**
     * Vendors whose offers may be shown and sold.
     *
     * Everything that lists a vendor offer to a customer goes through this, so
     * suspending a vendor takes their whole catalogue off the site in one
     * write rather than in one write per offer.
     */
    public function scopeSelling(Builder $query): Builder
    {
        return $query->where('status', self::APPROVED);
    }

    /**
     * What the platform currently owes, in integer Rial.
     *
     * The sum of the ledger, never a stored column. A balance that is written
     * down can disagree with its own history, and when it does there is no way
     * to find out which of the two is lying.
     */
    public function balance(): int
    {
        return (int) $this->ledger()->sum('amount');
    }

    /**
     * The part of that balance not already inside a settlement request.
     *
     * A vendor with 10,000,000 owed and 6,000,000 already requested may ask
     * for 4,000,000, not for 10,000,000 again.
     */
    public function availableBalance(): int
    {
        $spokenFor = (int) LedgerEntry::query()
            ->where('vendor_id', $this->id)
            ->whereIn('id', SettlementItem::query()
                ->select('ledger_entry_id')
                ->whereIn('settlement_id', Settlement::query()
                    ->select('id')
                    ->where('vendor_id', $this->id)
                    ->whereIn('status', [Settlement::REQUESTED, Settlement::APPROVED])))
            ->sum('amount');

        return $this->balance() - $spokenFor;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::PENDING => 'در انتظار تأیید',
            self::APPROVED => 'تأیید شده',
            self::SUSPENDED => 'معلق',
            self::REJECTED => 'رد شده',
            default => $this->status,
        };
    }
}
