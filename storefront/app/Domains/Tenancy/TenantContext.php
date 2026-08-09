<?php

namespace App\Domains\Tenancy;

use App\Domains\Tenancy\Contracts\Tenant;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * The active tenant for the current request.
 *
 * Bound as a singleton and set once, by the resolver middleware, before any
 * controller runs. Everything downstream — global query scopes, policies,
 * views, the URL generator for mini-store links — reads it from here rather
 * than passing a franchise id through every signature, which is the
 * arrangement that makes "forgot to pass the tenant" impossible to write.
 *
 * `set()` refuses to run twice *for the same request*. A request belongs to
 * one store; code that swaps the tenant mid-request is either a bug or an
 * attack, and either way the scopes and policies that already ran did so under
 * the old answer.
 *
 * The lock is keyed on the request rather than simply latched, because this
 * object outlives a single request in every environment that reuses a PHP
 * process — the test suite, a queue worker, Octane. A latch would let the
 * second request in a worker inherit the first one's shop, which is a worse
 * failure than the one the latch exists to prevent.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    private bool $locked = false;

    /** Identifies the request the current tenant was resolved for. */
    private ?int $token = null;

    public function set(Tenant $tenant, ?Request $request = null): void
    {
        $token = $request !== null ? spl_object_id($request) : null;

        if ($this->locked && $this->token === $token) {
            throw new RuntimeException('The tenant for this request is already resolved and cannot be changed.');
        }

        $this->tenant = $tenant;
        $this->locked = true;
        $this->token = $token;
    }

    public function current(): ?Tenant
    {
        return $this->tenant;
    }

    public function currentOrFail(): Tenant
    {
        return $this->tenant ?? throw new RuntimeException('No tenant is active for this request.');
    }

    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    public function isVendor(): bool
    {
        return $this->tenant?->tenantKind() === 'vendor';
    }

    public function isFranchise(): bool
    {
        return $this->tenant?->tenantKind() === 'franchise';
    }

    /** The id of the active tenant when it is of the given kind, else null. */
    public function idFor(string $kind): ?int
    {
        return $this->tenant !== null && $this->tenant->tenantKind() === $kind
            ? $this->tenant->tenantId()
            : null;
    }

    /**
     * Run a callback with no tenant active. Platform-side code — the admin
     * panel, reporting across all branches, a queued settlement run — needs to
     * see everything, and the global scopes are deliberately hard to bypass by
     * accident, so this is the one door.
     */
    public function withoutTenant(callable $callback): mixed
    {
        $tenant = $this->tenant;
        $locked = $this->locked;
        $token = $this->token;

        $this->tenant = null;
        $this->locked = false;
        $this->token = null;

        try {
            return $callback();
        } finally {
            $this->tenant = $tenant;
            $this->locked = $locked;
            $this->token = $token;
        }
    }

    /** Test seam. Never call this from application code. */
    public function reset(): void
    {
        $this->tenant = null;
        $this->locked = false;
        $this->token = null;
    }
}
