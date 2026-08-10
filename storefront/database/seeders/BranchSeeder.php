<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchDomain;
use Illuminate\Database\Seeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The central branch — the main store — and the hosts that reach it.
 *
 * Only this one. Franchise branches are real business relationships and get
 * created by a person in the central admin panel; inventing a Shiraz here
 * would put a shop on the internet that nobody agreed to open.
 */
class BranchSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $central = Branch::firstOrNew(['type' => Branch::CENTRAL]);

            // Only fill what is missing: this runs on every deploy, and a
            // name or an address edited in the panel must survive it.
            $central->fill([
                'slug' => $central->slug ?? 'central',
                'name' => $central->name ?? 'ویکی پلاس',
                'type' => Branch::CENTRAL,
                'presence' => $central->presence ?? 'both',
                'is_active' => true,
            ])->save();

            foreach (config('storefront.tenancy.central_hosts') as $i => $host) {
                try {
                    BranchDomain::firstOrCreate(
                        ['host' => strtolower($host)],
                        ['branch_id' => $central->id, 'is_primary' => $i === 0],
                    );
                } catch (UniqueConstraintViolationException) {
                    // The host already points at a branch. That is a decision
                    // somebody made in the panel and it outranks a default in
                    // a config file, so leave it where it is.
                }
            }
        });
    }
}
