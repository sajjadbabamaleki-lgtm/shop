<?php

namespace App\Domains\Tenancy\Concerns;

use App\Domains\Tenancy\Models\StoreDomain;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * The hostnames bound to a store (§4).
 *
 * Shared by Vendor and Franchise because a mini store's address works exactly
 * the same way for both — which is also why `store_domains` is one polymorphic
 * table rather than two.
 */
trait HasStoreDomains
{
    public function domains(): MorphMany
    {
        return $this->morphMany(StoreDomain::class, 'tenant');
    }

    /**
     * The address this store is linked to from outside it. Null before a
     * subdomain has been issued — a seller awaiting approval has no shop yet,
     * so it has no address either.
     */
    public function primaryDomain(): ?StoreDomain
    {
        return $this->domains()
            ->where('is_primary', true)
            ->whereNotNull('verified_at')
            ->first();
    }
}
