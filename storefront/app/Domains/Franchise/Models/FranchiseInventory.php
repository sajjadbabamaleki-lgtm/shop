<?php

namespace App\Domains\Franchise\Models;

use App\Domains\Tenancy\Concerns\BelongsToTenant;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A branch's local stock for one variant (§7).
 */
class FranchiseInventory extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'franchise_inventory';

    protected $guarded = ['sellable_stock'];

    protected function casts(): array
    {
        return [
            'stock_on_hand' => 'integer',
            'stock_reserved' => 'integer',
            'sellable_stock' => 'integer',
            'reorder_point' => 'integer',
        ];
    }

    public static function tenantKind(): string
    {
        return 'franchise';
    }

    public static function tenantColumn(): string
    {
        return 'franchise_id';
    }

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    public function needsReorder(): bool
    {
        return $this->sellable_stock <= $this->reorder_point;
    }
}
