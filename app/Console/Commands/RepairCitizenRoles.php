<?php

namespace App\Console\Commands;

use App\Models\Citizen;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

/**
 * Repair users who have a citizens row but are missing the Spatie citizen role.
 *
 * This fixes the 403 that dual-role users hit when the citizen upgrade flow
 * created the Citizen record but failed to assign the role.
 */
class RepairCitizenRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:repair-citizen-roles
                            {--dry-run : Show affected users without making changes}
                            {--email= : Repair a single user by email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Repair missing citizen Spatie roles for users with a Citizen profile';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $email  = $this->option('email');

        Role::firstOrCreate(
            ['name' => 'citizen'],
            ['guard_name' => config('auth.defaults.guard', 'web')]
        );

        if ($email) {
            return $this->repairUserByEmail($email, $dryRun);
        }

        return $this->repairAll($dryRun);
    }

    private function repairUserByEmail(string $email, bool $dryRun): int
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User not found: {$email}");
            return self::FAILURE;
        }

        if (! $user->citizen) {
            $this->warn("User has no Citizen profile: {$email}");
            return self::FAILURE;
        }

        if ($user->hasRole('citizen')) {
            $this->info("User already has citizen role: {$email}");
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY-RUN] Would assign' : 'Assigning') . " citizen role to {$email} (user_id: {$user->id}, citizen_id: {$user->citizen->id})");

        if (! $dryRun) {
            $user->assignRole('citizen');
        }

        return self::SUCCESS;
    }

    private function repairAll(bool $dryRun): int
    {
        $affected = User::whereHas('citizen')
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'citizen'))
            ->with('citizen:id,user_id')
            ->get();

        if ($affected->isEmpty()) {
            $this->info('No users found with a Citizen profile but missing citizen role.');
            return self::SUCCESS;
        }

        $count = $affected->count();
        $this->info("Found {$count} user(s) with a Citizen profile but missing citizen role.");

        foreach ($affected as $user) {
            $this->info(($dryRun ? '[DRY-RUN] Would assign' : 'Assigning') . " citizen role to {$user->email} (user_id: {$user->id}, citizen_id: {$user->citizen->id})");

            if (! $dryRun) {
                $user->assignRole('citizen');
            }
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
