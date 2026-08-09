<?php

namespace App\Domains\Tenancy;

use App\Domains\Franchise\Models\Franchise;
use App\Domains\Marketplace\Models\Vendor;
use App\Domains\Tenancy\Contracts\Tenant;
use App\Domains\Tenancy\Models\StoreDomain;

/**
 * Turns a hostname or a path prefix into the store that owns it (§4).
 *
 * Hostnames resolve through `store_domains` only — never by taking the first
 * label of the host and looking for a vendor or a franchise with that slug.
 * Vendor and franchise slugs are unique within their own tables and nothing
 * stops a branch called "shiraz" and a seller called "shiraz" existing at
 * once, so an implicit lookup would have to pick one, and it would pick the
 * wrong one eventually. A row in `store_domains` is created for every store
 * when it is created, so the explicit table costs nothing and cannot be
 * ambiguous.
 *
 * Resolution results are memoised per host for the life of the request; route
 * registration and the middleware both ask, and the answer cannot change in
 * between.
 */
class TenantResolver
{
    /** @var array<string, Tenant|false> */
    private array $hostCache = [];

    public function byHost(string $host): ?Tenant
    {
        $host = StoreDomain::normalise($host);

        if (array_key_exists($host, $this->hostCache)) {
            return $this->hostCache[$host] ?: null;
        }

        $tenant = null;

        if (! $this->isCentralHost($host)) {
            $domain = StoreDomain::query()->where('host', $host)->first();

            if ($domain?->isUsable()) {
                $candidate = $domain->tenant;
                $tenant = $candidate instanceof Tenant && $candidate->isStorefrontLive()
                    ? $candidate
                    : null;
            }
        }

        $this->hostCache[$host] = $tenant ?: false;

        return $tenant;
    }

    /**
     * The path fallback: /s/{slug} for a vendor, /f/{slug} for a branch.
     * Distinct prefixes rather than one shared namespace, for the same reason
     * hostnames go through a table — the two slug spaces are independent.
     */
    public function byPath(string $prefix, string $slug): ?Tenant
    {
        $tenant = match ($prefix) {
            config('tenancy.vendor_path_prefix') => Vendor::query()->where('slug', $slug)->first(),
            config('tenancy.franchise_path_prefix') => Franchise::query()->where('slug', $slug)->first(),
            default => null,
        };

        return $tenant?->isStorefrontLive() ? $tenant : null;
    }

    /**
     * True for the parent store's own hostnames: the central domain itself,
     * the subdomains we reserve for our own services, and anything that is not
     * under the central domain at all (localhost, preview URLs, the CI host).
     */
    public function isCentralHost(string $host): bool
    {
        $host = StoreDomain::normalise($host);
        $central = StoreDomain::normalise((string) config('tenancy.central_domain'));

        if ($host === $central) {
            return true;
        }

        if (! str_ends_with($host, '.'.$central)) {
            // Not under the central domain. It may still be a custom domain
            // bound to a store, so this is not "central" — the caller's
            // store_domains lookup decides.
            return false;
        }

        $label = substr($host, 0, -strlen('.'.$central));

        return in_array($label, (array) config('tenancy.reserved_subdomains', []), true);
    }
}
