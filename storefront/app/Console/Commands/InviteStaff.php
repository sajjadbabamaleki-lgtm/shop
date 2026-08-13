<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a member of staff and gives them a role.
 *
 * There is no sign-up for staff and there should not be: an account with power
 * over other people's orders is granted, not requested. Until the platform
 * panel exists this command is how the first one is made.
 *
 *   php artisan staff:invite ali@vikyplus.ir "علی" --role=admin
 *   php artisan staff:invite sara@vikyplus.ir "سارا" --role=franchise-manager --branch=shiraz
 *
 * A platform role goes on the user; a branch role goes on branch_users with
 * the branch beside it, which is what stops a franchise manager from being
 * granted platform-wide by accident.
 *
 * The password is generated and printed once. Nothing chooses a password on
 * somebody's behalf and stores it anywhere.
 */
class InviteStaff extends Command
{
    protected $signature = 'staff:invite
        {email : the address they sign in with}
        {name : their name}
        {--role=admin : a role slug — admin, franchise-owner, franchise-manager, franchise-staff…}
        {--branch= : the branch slug, required for a branch role}';

    protected $description = 'Create a staff account and grant it a role';

    public function handle(TenantContext $tenant): int
    {
        $role = Role::where('slug', $this->option('role'))->first();

        if ($role === null) {
            $this->error("There is no role «{$this->option('role')}». Run `php artisan db:seed --class=RolesAndPermissionsSeeder` if the list looks empty.");

            return self::FAILURE;
        }

        if ($role->scope === Role::SCOPE_BRANCH && ! $this->option('branch')) {
            $this->error("«{$role->slug}» is a branch role, so it needs --branch=<slug>. A branch role with no branch would mean nothing at all.");

            return self::FAILURE;
        }

        $branch = $this->option('branch')
            ? Branch::where('slug', $this->option('branch'))->first()
            : null;

        if ($this->option('branch') && $branch === null) {
            $this->error("There is no branch «{$this->option('branch')}».");

            return self::FAILURE;
        }

        $password = Str::password(14, symbols: false);

        $user = DB::transaction(function () use ($role, $branch, $password, $tenant) {
            $user = User::firstOrCreate(
                ['email' => $this->argument('email')],
                ['name' => $this->argument('name'), 'password' => $password],
            );

            if ($role->scope === Role::SCOPE_PLATFORM) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            } else {
                // Written inside the branch, so the row cannot end up belonging
                // to nobody — and updateOrCreate rather than create, because
                // somebody's role at a branch changes and a second row would
                // violate the one-role-per-person-per-branch index anyway.
                $tenant->forBranch($branch, fn () => BranchUser::updateOrCreate(
                    ['branch_id' => $branch->id, 'user_id' => $user->id],
                    ['role_id' => $role->id],
                ));
            }

            return $user;
        });

        $where = $branch ? " at «{$branch->name}»" : ' platform-wide';

        $this->info("«{$user->name}» can sign in at /admin as {$role->slug}{$where}.");

        if ($user->wasRecentlyCreated) {
            $this->line('');
            $this->line($password);
            $this->line('');
            $this->comment('That password is shown once. Give it to them, and have them change it.');
        } else {
            $this->comment('That account already existed, so its password was left alone.');
        }

        return self::SUCCESS;
    }
}
