<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Phone Verification Service
 *
 * Handles phone verification via OTP (one-time password) during registration.
 * Integrates with TwilioSmsService to send SMS codes.
 *
 * Workflow:
 * 1. User provides phone during registration
 * 2. sendVerificationCode() generates 6-digit code, hashes it, sends via SMS, stores to DB
 * 3. User receives SMS with code
 * 4. User submits code via verifyCode()
 * 5. Code is validated (expiry, max attempts, hash match)
 * 6. On success, phone_verified_at is set on the user
 */
class PhoneVerificationService
{
    public function __construct(
        private readonly TwilioSmsService $twilioService,
    ) {}

    /**
     * Send a verification code to the provided phone number.
     * Returns the code length (for debugging) and sends via SMS.
     *
     * @param  string  $phone
     * @param  User|null  $user (optional, for linking code to user_id)
     * @return bool Success of sending
     */
    public function sendVerificationCode(string $phone, ?User $user = null): bool
    {
        // Validate phone format
        if (!$this->twilioService->validatePhoneNumber($phone)) {
            Log::warning('Invalid phone format for verification code', ['phone' => substr($phone, -4)]);
            return false;
        }

        // Generate 6-digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $codeHash = Hash::make($code);

        // Revoke any existing unverified codes for this phone
        DB::table('phone_verification_codes')
            ->where('phone', $phone)
            ->whereNull('verified_at')
            ->delete();

        // Store new code with expiry (10 minutes)
        DB::table('phone_verification_codes')->insert([
            'phone'         => $phone,
            'code_hash'     => $codeHash,
            'attempts'      => 0,
            'max_attempts'  => 3,
            'expires_at'    => now()->addMinutes(10),
            'user_id'       => $user?->id,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Send SMS with code
        $message = "Your u9itus verification code is: {$code}. This code expires in 10 minutes. Do not share this code.";
        $sent = $this->twilioService->sendSms($user ?? new User(['email' => 'system']), $message, $phone);

        if ($sent) {
            Log::info('Phone verification code sent', [
                'phone'   => substr($phone, -4),
                'user_id' => $user?->id,
            ]);
        } else {
            Log::error('Failed to send phone verification code', [
                'phone'   => substr($phone, -4),
                'user_id' => $user?->id,
            ]);
        }

        return $sent;
    }

    /**
     * Verify the phone code provided by the user.
     * Returns true if valid, false otherwise.
     * Marks phone_verified_at if user provided.
     *
     * @param  string  $phone
     * @param  string  $code (the raw 6-digit code from user)
     * @param  User|null  $user (to update phone_verified_at)
     * @return bool
     */
    public function verifyCode(string $phone, string $code, ?User $user = null): bool
    {
        $isValid = false;

        // Retrieve the most recent unverified code for this phone
        $record = DB::table('phone_verification_codes')
            ->where('phone', $phone)
            ->whereNull('verified_at')
            ->orderByDesc('created_at')
            ->first();

        if (!$record) {
            Log::warning('No verification code found for phone', [
                'phone' => substr($phone, -4),
            ]);
        } elseif (now()->greaterThan($record->expires_at)) {
            DB::table('phone_verification_codes')
                ->where('id', $record->id)
                ->delete();
            Log::warning('Verification code expired', [
                'phone' => substr($phone, -4),
            ]);
        } elseif ($record->attempts >= $record->max_attempts) {
            DB::table('phone_verification_codes')
                ->where('id', $record->id)
                ->delete();
            Log::warning('Verification code max attempts exceeded', [
                'phone' => substr($phone, -4),
            ]);
        } else {
            DB::table('phone_verification_codes')
                ->where('id', $record->id)
                ->increment('attempts');

            if (!Hash::check($code, $record->code_hash)) {
                Log::warning('Verification code mismatch', [
                    'phone'    => substr($phone, -4),
                    'attempts' => $record->attempts + 1,
                ]);
            } else {
                DB::table('phone_verification_codes')
                    ->where('id', $record->id)
                    ->update([
                        'verified_at' => now(),
                        'updated_at'  => now(),
                    ]);

                if ($user) {
                    $user->update(['phone_verified_at' => now()]);
                    Log::info('Phone verified for user', [
                        'user_id' => $user->id,
                        'phone'   => substr($phone, -4),
                    ]);
                } else {
                    Log::info('Phone verification code validated', [
                        'phone' => substr($phone, -4),
                    ]);
                }

                $isValid = true;
            }
        }

        return $isValid;
    }

    /**
     * Check if a phone is already verified by a user.
     */
    public function isPhoneVerified(string $phone): bool
    {
        return DB::table('phone_verification_codes')
            ->where('phone', $phone)
            ->whereNotNull('verified_at')
            ->exists();
    }

    /**
     * Get the verification record for a phone (if exists).
     */
    public function getVerificationRecord(string $phone)
    {
        return DB::table('phone_verification_codes')
            ->where('phone', $phone)
            ->whereNotNull('verified_at')
            ->orderByDesc('verified_at')
            ->first();
    }
}
