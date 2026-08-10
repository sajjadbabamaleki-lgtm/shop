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

            foreach ($this->centralHosts() as $i => $host) {
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

    /**
     * Every host the main store should answer on.
     *
     * The configured list, plus **the host the application says it is**. That
     * second part is what stops the most likely first-deploy failure there is:
     * a platform hands the app an address of its own — something.liara.run —
     * and if nothing has told the storefront that address belongs to a branch,
     * every single page returns 404 and the site looks completely broken for
     * a reason nobody would guess from the symptom.
     *
     * APP_URL has to be set correctly anyway, so deriving from it costs
     * nothing and removes a whole class of that failure.
     *
     * @return list<string>
     */
    private function centralHosts(): array
    {
        $hosts = config('storefront.tenancy.central_hosts', []);

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (is_string($appHost) && $appHost !== '') {
            $hosts[] = $appHost;
        }

        return array_values(array_unique(array_map('strtolower', $hosts)));
    }
}
