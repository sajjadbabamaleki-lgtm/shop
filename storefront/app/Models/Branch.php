<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A store: the brand's own central operation, or a franchise branch.
 *
 * Not itself branch-scoped — a branch is the tenant, not something a tenant
 * owns — so central administration lists these without an escape hatch, and
 * authorization decides who may see which.
 */
class Branch extends Model
{
    use HasFactory, RecordsAudits;

    public const CENTRAL = 'central';

    public const FRANCHISE = 'franchise';

    protected $fillable = [
        'slug', 'name', 'type', 'presence', 'province', 'city',
        'address', 'phone', 'email', 'business_hours', 'is_active',
    ];

    protected function casts(): array
    {
        return ['business_hours' => 'array', 'is_active' => 'boolean'];
    }

    public function domains(): HasMany
    {
        return $this->hasMany(BranchDomain::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(BranchUser::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isCentral(): bool
    {
        return $this->type === self::CENTRAL;
    }

    public function isFranchise(): bool
    {
        return $this->type === self::FRANCHISE;
    }

    public function scopeFranchises(Builder $query): Builder
    {
        return $query->where('type', self::FRANCHISE);
    }

    /**
     * The brand's own store. One row, guaranteed by a partial unique index.
     */
    public static function central(): self
    {
        return self::where('type', self::CENTRAL)->firstOrFail();
    }

    public function primaryDomain(): ?BranchDomain
    {
        return $this->domains->firstWhere('is_primary', true) ?? $this->domains->first();
    }
}
