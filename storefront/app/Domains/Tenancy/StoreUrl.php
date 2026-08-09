<?php

namespace App\Domains\Tenancy;

use App\Domains\Tenancy\Contracts\Tenant;

/**
 * A mini store's address, seen from outside it.
 *
 * Inside a mini store nothing needs this: `route('store.home')` already
 * produces the right thing, because the route file is registered once per
 * request in whichever form that request arrived by. This exists for the two
 * places that look at a shop from the parent side — the seller directory and
 * the panel's "view my shop" link — where the tenant is not the active one.
 *
 * A store with a bound hostname is always linked to by hostname. The path form
 * works, but handing a seller `vikyplus.ir/s/their-shop` as their address
 * would defeat the point of giving them one.
 */
class StoreUrl
{
    public static function home(Tenant $tenant): string
    {
        $host = static::hostFor($tenant);

        if ($host !== null) {
            return 'https://'.$host.'/';
        }

        return route('store.home', static::pathParameters($tenant));
    }

    public static function product(Tenant $tenant, string $productSlug): string
    {
        $host = static::hostFor($tenant);

        if ($host !== null) {
            return 'https://'.$host.'/products/'.$productSlug;
        }

        return route('store.product', static::pathParameters($tenant) + ['product' => $productSlug]);
    }

    /** @return array{tenantPrefix: string, tenantSlug: string} */
    private static function pathParameters(Tenant $tenant): array
    {
        return [
            'tenantPrefix' => $tenant->tenantKind() === 'vendor'
                ? config('tenancy.vendor_path_prefix')
                : config('tenancy.franchise_path_prefix'),
            'tenantSlug' => $tenant->tenantSlug(),
        ];
    }

    private static function hostFor(Tenant $tenant): ?string
    {
        $domain = $tenant->domains()
            ->where('is_primary', true)
            ->whereNotNull('verified_at')
            ->first();

        return $domain?->host;
    }
}
