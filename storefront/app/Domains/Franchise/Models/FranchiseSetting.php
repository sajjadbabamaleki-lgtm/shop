<?php

namespace App\Domains\Franchise\Models;

use App\Domains\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-branch operational configuration: shipping settings, working hours,
 * contact block (§3).
 */
class FranchiseSetting extends Model
{
    use BelongsToTenant, HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'array'];
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
}
