<?php

namespace App\Support\Tenancy;

use App\Models\Branch;
use App\Models\User;

/**
 * Which branch a member of staff is working at.
 *
 * This used to live inside `ResolveAdminTenant` and be reachable only by
 * running that middleware, which tied two separate questions together:
 *
 *   1. **Which branch is this page about?** — the tenant. The platform screens
 *      answer «none», deliberately: a staff account is not a branch's
 *      property, and the owner must be able to set a password for somebody at
 *      a shop they have never visited. `ResolveAdminTenant` does not run
 *      there, nothing is bound, and branch-scoped models fail closed.
 *
 *   2. **Which branch is this person's?** — which the shell needs on *every*
 *      screen, because the sidebar, the bottom bar and the bell are the
 *      panel's furniture rather than the page's. With the two questions
 *      answered by one middleware, the platform screens got «none» to both:
 *      the phone's bottom bar lost «خانه» and «سفارش‌ها» and came out three
 *      items wide on six screens, and the bell went quiet.
 *
 * So the answer moves here, and both callers ask the same object. Two copies
 * of this decision is how they end up disagreeing about which shop somebody is
 * standing in — the same reason `Role::FULL_ACCESS` is one list.
 *
 * The choice is kept in the **session**, never read from the request. A branch
 * manager gets the branch they actually work at and has no way to ask for
 * another; a platform administrator, whose authority genuinely covers every
 * branch, may pick one from the switcher. A link cannot make either choice for
 * them.
 */
class AdminBranch
{
    public function for(User $user): ?Branch
    {
        $chosen = session('admin.branch');

        // Platform-wide authority: may work at any branch.
        if ($user->hasPermissionTo('branch.view')) {
            $branch = is_int($chosen) || is_string($chosen)
                ? Branch::find($chosen)
                : null;

            return $branch
                ?? Branch::where('type', Branch::CENTRAL)->first()
                ?? Branch::first();
        }

        // Everybody else: the branch they actually work at. Two jobs at two
        // branches means two rows, and the first is a starting point they can
        // change from the switcher — which only ever offers branches they are
        // already staff of.
        $roles = $user->branchRoles;

        return $roles->firstWhere('branch_id', $chosen)?->branch
            ?? $roles->first()?->branch;
    }
}
