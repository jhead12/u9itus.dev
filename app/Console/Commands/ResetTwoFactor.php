<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResetTwoFactor extends Command
{
    protected $signature = 'auth:reset-2fa
                            {email : Email address of the account to clear 2FA for}
                            {--admin : Clear admin 2FA (admin_two_factor_*) instead of generic 2FA (two_factor_*)}
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Clear a stuck user\'s two-factor secret, confirmation, and recovery codes so they can re-enroll.';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $admin = (bool) $this->option('admin');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("No user found with email {$email}.");
            return self::FAILURE;
        }

        [$secretCol, $confirmedCol, $recoveryCol] = $admin
            ? ['admin_two_factor_secret', 'admin_two_factor_confirmed_at', 'admin_two_factor_recovery_codes']
            : ['two_factor_secret', 'two_factor_confirmed_at', 'two_factor_recovery_codes'];

        $enabled = $admin ? $user->hasAdminTwoFactorEnabled() : $user->hasTwoFactorEnabled();
        $recoveryCount = is_array($user->{$recoveryCol}) ? count($user->{$recoveryCol}) : 0;

        $this->line('User: ' . $user->email . ' (id=' . $user->id . ', user_type=' . $user->user_type . ')');
        $this->line(($admin ? 'Admin' : 'Generic') . " 2FA enabled: " . ($enabled ? 'yes' : 'no'));
        $this->line('Confirmed at: ' . ($user->{$confirmedCol} ?? 'null'));
        $this->line('Unused recovery codes: ' . $recoveryCount);

        if (!$enabled && $user->{$secretCol} === null) {
            $this->info('Nothing to clear — ' . ($admin ? 'admin' : 'generic') . ' 2FA is already unset for this account.');
            return self::SUCCESS;
        }

        if (!$this->option('force')) {
            $confirmed = $this->confirm(
                "Clear {$this->flowLabel($admin)} 2FA for {$user->email}? They will need to re-enroll on next login."
            );

            if (!$confirmed) {
                $this->info('Aborted — no changes made.');
                return self::SUCCESS;
            }
        }

        $user->forceFill([
            $secretCol => null,
            $confirmedCol => null,
            $recoveryCol => null,
        ])->save();

        Log::warning('auth:reset-2fa cleared stuck 2FA via console', [
            'user_id' => $user->id,
            'email' => $user->email,
            'flow' => $admin ? 'admin' : 'generic',
        ]);

        $this->info("Cleared {$this->flowLabel($admin)} 2FA for {$user->email}. They can re-enroll from " . ($admin ? '/admin/security/2fa' : '/2fa/setup') . '.');

        return self::SUCCESS;
    }

    private function flowLabel(bool $admin): string
    {
        return $admin ? 'admin' : 'generic';
    }
}
