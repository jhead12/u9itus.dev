<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create
                            {--email= : Admin email address}
                            {--name= : Admin full name}
                            {--password= : Admin password}';

    protected $description = 'Create or update an admin user and ensure the admin role is assigned';

    public function handle(): int
    {
        $email    = $this->option('email')    ?? $this->ask('Email address');
        $name     = $this->option('name')     ?? $this->ask('Full name', 'Admin User');
        $password = $this->option('password') ?? $this->secret('Password');

        // Validate inputs
        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => 'required|email', 'password' => 'required|min:8'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        // Ensure the admin role exists (in case seeders haven't run)
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']);

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name'              => $name,
                'first_name'        => explode(' ', $name)[0],
                'last_name'         => implode(' ', array_slice(explode(' ', $name), 1)) ?: 'User',
                'password'          => Hash::make($password),
                'user_type'         => 'admin',
                'email_verified_at' => now(),
                'is_verified'       => true,
                'kyc_status'        => 'approved',
            ]
        );

        // Revoke any non-admin roles then assign admin
        $user->syncRoles(['admin']);

        $this->info("✓ Admin user {$email} created/updated successfully.");
        $this->line("  Roles: " . $user->getRoleNames()->implode(', '));

        return self::SUCCESS;
    }
}
