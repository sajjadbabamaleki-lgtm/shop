<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Contracts\Tenant;
use App\Domains\Tenancy\Models\StoreDomain;
use RuntimeException;

/**
 * Gives a new store its address (§4).
 *
 * Every store gets a subdomain row the moment it is created, which is what
 * lets the resolver be a single indexed lookup on `host` with no fallback to
 * guessing from slugs. Subdomains are ours to issue, so they are trusted the
 * moment they are written. A custom domain is the customer's claim about DNS
 * we do not control, so it is written unverified and stays unusable until it
 * is proved.
 */
class StoreProvisioner
{
    public function provisionSubdomain(Tenant $tenant, ?string $label = null): StoreDomain
    {
        $label = $label ?? $tenant->tenantSlug();
        $host = $label.'.'.StoreDomain::normalise((string) config('tenancy.central_domain'));

        if (in_array($label, (array) config('tenancy.reserved_subdomains', []), true)) {
            throw new RuntimeException("The subdomain '{$label}' is reserved for the platform.");
        }

        $existing = StoreDomain::query()->where('host', $host)->first();

        if ($existing !== null && ! $this->belongsTo($existing, $tenant)) {
            throw new RuntimeException("The subdomain '{$host}' is already taken.");
        }

        return StoreDomain::query()->updateOrCreate(
            ['host' => $host],
            [
                'tenant_type' => $tenant->tenantKind(),
                'tenant_id' => $tenant->tenantId(),
                'kind' => 'subdomain',
                'is_primary' => true,
                'verified_at' => now(),
            ],
        );
    }

    /**
     * Register a custom domain. It resolves nothing until `verify()` runs —
     * otherwise anyone could point our resolver at a hostname they do not own.
     */
    public function addCustomDomain(Tenant $tenant, string $host): StoreDomain
    {
        $host = StoreDomain::normalise($host);

        $existing = StoreDomain::query()->where('host', $host)->first();

        if ($existing !== null && ! $this->belongsTo($existing, $tenant)) {
            throw new RuntimeException("The domain '{$host}' is already bound to another store.");
        }

        return StoreDomain::query()->updateOrCreate(
            ['host' => $host],
            [
                'tenant_type' => $tenant->tenantKind(),
                'tenant_id' => $tenant->tenantId(),
                'kind' => 'custom',
                'is_primary' => false,
                'verification_token' => bin2hex(random_bytes(16)),
            ],
        );
    }

    public function verify(StoreDomain $domain): StoreDomain
    {
        $domain->forceFill(['verified_at' => now()])->save();

        return $domain;
    }

    private function belongsTo(StoreDomain $domain, Tenant $tenant): bool
    {
        return $domain->tenant_type === $tenant->tenantKind()
            && (int) $domain->tenant_id === $tenant->tenantId();
    }
}
