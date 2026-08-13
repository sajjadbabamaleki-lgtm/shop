<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registers a company and the person who signs in for it.
 *
 *   php artisan vendor:invite kafsh-parsian "کفش پارسیان" ali@parsian.ir "علی"
 *
 * The vendor starts **pending**, which is §4: nothing they list is on the site
 * until a person approves them. Approving is a screen in the panel, not a flag
 * on this command, because letting somebody else sell on your platform is a
 * decision and not a piece of setup.
 */
class InviteVendor extends Command
{
    protected $signature = 'vendor:invite
        {slug : short latin name, used internally}
        {name : the company, as customers will read it}
        {email : the address their first user signs in with}
        {contact : that person\'s name}
        {--phone= : the company\'s phone number}
        {--iban= : where settlements are paid}';

    protected $description = 'Register a marketplace vendor and its first user';

    public function handle(): int
    {
        if (Vendor::where('slug', $this->argument('slug'))->exists()) {
            $this->error("A vendor called «{$this->argument('slug')}» already exists.");

            return self::FAILURE;
        }

        $role = Role::where('slug', Role::VENDOR)->first();

        if ($role === null) {
            $this->error('The vendor role is missing. Run `php artisan db:seed --class=RolesAndPermissionsSeeder`.');

            return self::FAILURE;
        }

        $password = Str::password(14, symbols: false);

        [$vendor, $user] = DB::transaction(function () use ($role, $password) {
            $vendor = Vendor::create([
                'slug' => $this->argument('slug'),
                'name' => $this->argument('name'),
                'phone' => $this->option('phone'),
                'email' => $this->argument('email'),
                'iban' => $this->option('iban'),
                'status' => Vendor::PENDING,
            ]);

            $user = User::firstOrCreate(
                ['email' => $this->argument('email')],
                ['name' => $this->argument('contact'), 'password' => $password],
            );

            VendorUser::updateOrCreate(
                ['vendor_id' => $vendor->id, 'user_id' => $user->id],
                ['role_id' => $role->id],
            );

            return [$vendor, $user];
        });

        $this->info("«{$vendor->name}» is registered and waiting for approval. {$user->name} signs in at /admin/login and works at /vendor.");

        if ($user->wasRecentlyCreated) {
            $this->line('');
            $this->line($password);
            $this->line('');
            $this->comment('That password is shown once.');
        }

        return self::SUCCESS;
    }
}
