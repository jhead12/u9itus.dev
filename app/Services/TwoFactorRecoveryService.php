<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Self-service SMS recovery for stuck generic (non-admin) 2FA.
 *
 * Lets a user with a verified phone number disable their own TOTP 2FA when
 * they've lost both their authenticator and their recovery codes, without
 * needing the auth:reset-2fa artisan command run over SSH. This is a
 * one-time reset, not an ongoing alternate 2FA factor — a successful code
 * clears the user's two_factor_* columns entirely.
 */
class TwoFactorRecoveryService
{
    public function __construct(
        private readonly TwilioSmsService $twilioService,
    ) {}

    public function sendRecoveryCode(User $user): bool
    {
        if (!$user->phone || !$user->phone_verified_at) {
            Log::warning('2FA SMS recovery requested without a verified phone', [
                'user_id' => $user->id,
            ]);

            return false;
        }

        if (!$user->hasTwoFactorEnabled()) {
            return false;
        }

        $cooldownSeconds = (int) config('platform.standalone.auth.two_factor.recovery_sms.resend_cooldown_seconds', 60);

        $recent = DB::table('two_factor_recovery_sms_codes')
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->orderByDesc('created_at')
            ->first();

        if ($recent && now()->lessThan(Carbon::parse($recent->created_at)->addSeconds($cooldownSeconds))) {
            return false;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $codeHash = Hash::make($code);
        $ttlMinutes = (int) config('platform.standalone.auth.two_factor.recovery_sms.code_ttl_minutes', 10);
        $maxAttempts = (int) config('platform.standalone.auth.two_factor.recovery_sms.max_attempts', 5);

        DB::table('two_factor_recovery_sms_codes')
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->delete();

        DB::table('two_factor_recovery_sms_codes')->insert([
            'user_id' => $user->id,
            'phone' => $user->phone,
            'code_hash' => $codeHash,
            'attempts' => 0,
            'max_attempts' => $maxAttempts,
            'expires_at' => now()->addMinutes($ttlMinutes),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $message = "Your u9itus 2FA recovery code is: {$code}. This code expires in {$ttlMinutes} minutes and will disable your current two-factor authentication. If you didn't request this, ignore this message.";
        $sent = $this->twilioService->sendSms($user, $message, $user->phone);

        if ($sent) {
            Log::info('2FA SMS recovery code sent', [
                'user_id' => $user->id,
                'phone' => substr((string) $user->phone, -4),
            ]);
        } else {
            Log::error('Failed to send 2FA SMS recovery code', [
                'user_id' => $user->id,
                'phone' => substr((string) $user->phone, -4),
            ]);
        }

        return $sent;
    }

    public function verifyAndDisable(User $user, string $code): bool
    {
        $record = DB::table('two_factor_recovery_sms_codes')
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->orderByDesc('created_at')
            ->first();

        if (!$record) {
            Log::warning('No 2FA SMS recovery code found', ['user_id' => $user->id]);
            return false;
        }

        if (now()->greaterThan($record->expires_at)) {
            Log::warning('2FA SMS recovery code expired', ['user_id' => $user->id]);
            return false;
        }

        if ($record->attempts >= $record->max_attempts) {
            Log::warning('2FA SMS recovery code max attempts exceeded', ['user_id' => $user->id]);
            return false;
        }

        DB::table('two_factor_recovery_sms_codes')
            ->where('id', $record->id)
            ->increment('attempts');

        if (!Hash::check($code, $record->code_hash)) {
            Log::warning('2FA SMS recovery code mismatch', [
                'user_id' => $user->id,
                'attempts' => $record->attempts + 1,
            ]);

            return false;
        }

        DB::table('two_factor_recovery_sms_codes')
            ->where('id', $record->id)
            ->update(['consumed_at' => now(), 'updated_at' => now()]);

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        Log::warning('2FA disabled via self-service SMS recovery', [
            'user_id' => $user->id,
            'email' => $user->email,
            'flow' => 'sms_recovery',
        ]);

        return true;
    }
}
