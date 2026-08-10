<?php

namespace App\Support\Tenancy;

use App\Models\Branch;
use RuntimeException;

/**
 * Which branch this request belongs to.
 *
 * Bound as a singleton for the life of the request, so every query scope,
 * policy and view answers the same question the same way — spec §17's "the
 * active tenant context should be available consistently throughout the
 * request".
 *
 * Two rules, and both matter more than they look:
 *
 * 1. **Nothing infers the tenant from a parameter.** It is set once, by the
 *    middleware from the request's host, or in an administration context from
 *    the signed-in user's own branch. A branch_id arriving in a form or a URL
 *    is never allowed to decide this — that is §18's whole point and §30's
 *    "never trust branch_id sent from the frontend".
 *
 * 2. **No tenant means no rows, not all rows.** A branch-scoped query with
 *    nothing bound returns nothing. Failing closed turns "somebody forgot the
 *    middleware" into an empty page instead of into Shiraz reading Tehran's
 *    stock. Central administration, which legitimately sees everything, has to
 *    say so out loud through withoutScoping().
 */
class TenantContext
{
    private ?Branch $branch = null;

    private bool $scoping = true;

    public function set(Branch $branch): void
    {
        $this->branch = $branch;
    }

    public function forget(): void
    {
        $this->branch = null;
    }

    public function has(): bool
    {
        return $this->branch !== null;
    }

    public function branch(): Branch
    {
        return $this->branch ?? throw new RuntimeException(
            'No branch is bound for this request. Either the tenant middleware did not run, '
            .'or this is an administration context that should resolve the branch from the user.'
        );
    }

    public function branchOrNull(): ?Branch
    {
        return $this->branch;
    }

    public function id(): ?int
    {
        return $this->branch?->id;
    }

    public function isCentral(): bool
    {
        return $this->branch?->isCentral() ?? false;
    }

    /**
     * Whether branch-scoped queries should currently be constrained.
     */
    public function scoping(): bool
    {
        return $this->scoping;
    }

    /**
     * Run something across every branch.
     *
     * For central administration and for seeders — the places that genuinely
     * need to see all of it. Deliberately awkward to reach and easy to grep
     * for, because every call is a place where isolation is being set aside on
     * purpose and somebody should be able to find them all.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withoutScoping(callable $callback): mixed
    {
        $previous = $this->scoping;
        $this->scoping = false;

        try {
            return $callback();
        } finally {
            $this->scoping = $previous;
        }
    }

    /**
     * Run something as if the request had arrived at another branch.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function forBranch(Branch $branch, callable $callback): mixed
    {
        $previous = $this->branch;
        $this->branch = $branch;

        try {
            return $callback();
        } finally {
            $this->branch = $previous;
        }
    }
}
