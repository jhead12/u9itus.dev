<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ResetAdminPassword extends Command
{
    protected $signature = 'admin:reset-password
                            {--email= : Admin email address}
                            {--password= : New password (optional, will prompt if not provided)}';

    protected $description = 'Reset the password for an existing admin user';

    public function handle(): int
    {
        $email    = $this->option('email') ?? $this->ask('Admin email address');
        $password = $this->option('password') ?? $this->secret('New password');

        // Validate inputs
        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            [
                'email'    => 'required|email',
                'password' => 'required|min:8',
            ],
            [
                'password.min' => 'Password must be at least 8 characters.',
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        // Find the admin user
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("✗ No user found with email: {$email}");
            $this->line('  Use `php artisan admin:create` to create a new admin account.');
            return self::FAILURE;
        }

        // Verify the user has admin role
        if (! $user->hasRole('admin')) {
            $this->error("✗ User {$email} exists but is not an admin.");
            $this->line("  Current roles: " . $user->getRoleNames()->implode(', '));
            $this->line('  Use `php artisan admin:create` to grant admin access.');
            return self::FAILURE;
        }

        // Confirm the reset
        if (! $this->confirm("Reset password for admin user: {$user->name} ({$email})?", true)) {
            $this->line('Password reset cancelled.');
            return self::SUCCESS;
        }

        // Update the password
        $user->forceFill([
            'password' => Hash::make($password),
        ])->save();

        $this->info("✓ Password reset successfully for {$email}");

        // Send notification email (non-fatal)
        try {
            Mail::to($user->email)
                ->send(new \App\Mail\AdminPasswordResetMail($user));
            $this->line("  ✉  Notification email sent to {$email}.");
        } catch (\Exception $e) {
            $this->warn("  ⚠  Could not send notification email: " . $e->getMessage());
        }

        $this->newLine();
        $this->line('You can now log in at:');
        $this->line('  ' . config('app.url') . '/admin/login');

        return self::SUCCESS;
    }
}
