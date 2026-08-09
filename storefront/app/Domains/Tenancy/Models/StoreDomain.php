<?php

namespace App\Domains\Tenancy\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A hostname bound to a mini store.
 *
 * `normalise()` is the reason the unique index on `host` is worth anything:
 * every write and every lookup goes through it, so "Shiraz.VikyPlus.ir:8443"
 * and "shiraz.vikyplus.ir" cannot become two rows pointing at two tenants.
 */
class StoreDomain extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function tenant(): MorphTo
    {
        return $this->morphTo();
    }

    public function isUsable(): bool
    {
        return $this->kind === 'subdomain' || $this->verified_at !== null;
    }

    public static function normalise(string $host): string
    {
        $host = mb_strtolower(trim($host));
        $host = preg_replace('#^https?://#', '', $host);
        $host = explode('/', $host)[0];
        $host = explode(':', $host)[0];

        return rtrim($host, '.');
    }

    public function setHostAttribute(string $value): void
    {
        $this->attributes['host'] = static::normalise($value);
    }
}
