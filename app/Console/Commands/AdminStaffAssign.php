<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\StaffAccessService;
use App\Support\AdminAccess;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AdminStaffAssign extends Command
{
    protected $signature = 'admin:staff:assign
                            {email : Existing, verified account to assign staff access to}
                            {--role=* : Staff role label to assign, e.g. "Social Reviewer"; repeat for multiple. Omit all to revoke operational access}
                            {--owner : Also grant Super Admin}
                            {--actor= : Email of the Super Admin performing this change (required)}';

    protected $description = 'Assign or revoke delegated staff roles (and Super Admin) on an existing account. '
        . 'Goes through the same StaffAccessService used by the staff-access admin page, '
        . 'so authorization, last-owner protection, and the audit log all behave identically.';

    public function handle(StaffAccessService $service): int
    {
        $actor = $this->resolveActor();
        if (! $actor) {
            return self::FAILURE;
        }

        $target = User::where('email', $this->argument('email'))->first();
        if (! $target) {
            $this->error('✗ No user found with email: '.$this->argument('email'));
            return self::FAILURE;
        }

        $labels = $this->option('role');
        $roleNames = array_map(fn ($label) => 'staff:'.trim($label), $labels);
        $roles = Role::where('guard_name', 'web')->where('name', 'like', 'staff:%')->whereIn('name', $roleNames)->get();

        if ($roles->count() !== count(array_unique($roleNames))) {
            $missing = array_diff($roleNames, $roles->pluck('name')->all());
            $this->error('✗ Unknown staff role(s): '.implode(', ', array_map(fn ($n) => substr($n, 6), $missing)));
            $this->line('  Run `php artisan admin:staff:list` to see available roles.');
            return self::FAILURE;
        }

        $owner = $this->option('owner');
        if (! $roles->count() && ! $owner
            && ! $this->confirm("No roles given for {$target->email} — this revokes all operational staff access. Continue?")) {
            $this->line('Cancelled.');
            return self::SUCCESS;
        }

        try {
            $service->assign($actor, $target, $roles->pluck('id')->all(), $owner);
            $target->refresh();
            $this->info("✓ Access updated for {$target->email}.");
            $this->line('  Effective roles: '.$target->getRoleNames()->implode(', '));
            return self::SUCCESS;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error("✗ {$message}");
                }
            }
            return self::FAILURE;
        } catch (HttpException $e) {
            $this->error("✗ Not permitted: {$actor->email} is not a Super Admin.");
            return self::FAILURE;
        }
    }

    private function resolveActor(): ?User
    {
        $email = $this->option('actor');
        if (! $email) {
            $this->error('✗ --actor=<email> is required. The action is authorized and audited as that Super Admin.');
            return null;
        }
        $actor = User::where('email', $email)->first();
        if (! $actor) {
            $this->error("✗ No user found with email: {$email}");
            return null;
        }
        if (! AdminAccess::owner($actor)) {
            $this->error("✗ {$email} is not a Super Admin. Only a Super Admin can assign staff access.");
            return null;
        }
        return $actor;
    }
}
