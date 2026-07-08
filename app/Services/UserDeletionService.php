<?php

namespace App\Services;

use App\Models\AdminSecurityAuditLog;
use App\Models\Citizen;
use App\Models\DeletedAccount;
use App\Models\Politician;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UserDeletionService
{
    /**
     * Archive the user into deleted_accounts then hard-delete all associated rows.
     *
     * @throws \Throwable
     */
    public function archiveAndDelete(
        User $user,
        ?User $deletedBy = null,
        ?string $reason = null,
        ?string $ip = null
    ): DeletedAccount {
        if ($user->user_type === 'admin') {
            throw new \RuntimeException('Admin accounts cannot be deleted through this service.');
        }

        return DB::transaction(function () use ($user, $deletedBy, $reason, $ip) {
            // Snapshot the user row before anything is touched
            $snapshot = $user->getAttributes();

            // Resolve linked profile IDs
            $voter     = $user->voter;
            $politician = $user->politician;
            $citizen   = $user->citizen ?? null;

            // Archive record
            $record = DeletedAccount::create([
                'original_user_id'  => $user->id,
                'email'             => $user->email,
                'first_name'        => $user->first_name,
                'last_name'         => $user->last_name,
                'user_type'         => $user->user_type,
                'platform'          => $user->platform,
                'registration_ip'   => $user->registration_ip,
                'voter_id'          => $voter?->id,
                'politician_id'     => $politician?->id,
                'citizen_id'        => $citizen?->id,
                'user_snapshot'     => array_merge($snapshot, [
                    // full_name lives on the profile table, not users — snapshot it here
                    // so restore can reconstruct the profile row without a DB NOT NULL error.
                    '_voter_full_name'   => $voter?->full_name,
                    '_citizen_full_name' => $citizen?->full_name,
                ]),
                'deleted_by_user_id' => $deletedBy?->id,
                'deletion_reason'   => $reason,
                'deleted_by_ip'     => $ip,
                'deleted_at'        => now(),
            ]);

            // Delete profiles (cascades their child rows via DB constraints)
            $voter?->delete();
            $politician?->delete();
            $citizen?->delete();

            // Delete user (cascades notification_preferences, onboarding_progress, etc.)
            $user->delete();

            // Clean up email-keyed tables that have no FK constraint
            DB::table('password_reset_tokens')->where('email', $record->email)->delete();
            DB::table('mailing_list_subscribers')->where('email', $record->email)->delete();
            DB::table('registration_attempts')->where('email', $record->email)->delete();

            // Audit log
            AdminSecurityAuditLog::record(
                $deletedBy ?? $user,
                'user_deleted',
                [
                    'deleted_user_id' => $record->original_user_id,
                    'email'           => $record->email,
                    'user_type'       => $record->user_type,
                    'deleted_account_id' => $record->id,
                ]
            );

            Log::info('UserDeletionService: account deleted', [
                'deleted_account_id' => $record->id,
                'email'              => $record->email,
                'deleted_by'         => $deletedBy?->id,
            ]);

            return $record;
        });
    }

    /**
     * Restore a deleted account as a new user (Option B — new user_id).
     * Sends a password reset email so the user can set a new password.
     *
     * @throws \Throwable
     */
    public function restore(
        DeletedAccount $record,
        User $restoredBy,
        ?string $ip = null
    ): User {
        if ($record->isRestored()) {
            throw new \RuntimeException('This account has already been restored.');
        }

        if (User::where('email', $record->email)->exists()) {
            throw new \RuntimeException("A user with email {$record->email} already exists. Cannot restore.");
        }

        return DB::transaction(function () use ($record, $restoredBy, $ip) {
            $snapshot = $record->user_snapshot;

            // Create new user row — new auto-increment id, password reset required
            $user = User::create([
                'name'              => ($snapshot['first_name'] ?? '') . ' ' . ($snapshot['last_name'] ?? ''),
                'email'             => $record->email,
                'first_name'        => $record->first_name,
                'last_name'         => $record->last_name,
                'user_type'         => $record->user_type ?? 'voter',
                'platform'          => $record->platform ?? 'standalone',
                'phone'             => $snapshot['phone'] ?? null,
                'street_address'    => $snapshot['street_address'] ?? null,
                'city'              => $snapshot['city'] ?? null,
                'state'             => $snapshot['state'] ?? null,
                'zip_code'          => $snapshot['zip_code'] ?? null,
                'country'           => $snapshot['country'] ?? null,
                'kyc_status'        => 'pending', // reset — must re-verify
                'is_verified'       => false,
                'password'          => Hash::make(Str::random(32)), // unusable until reset
                'registration_ip'   => $record->registration_ip,
            ]);

            // Restore a bare voter profile if the original was a voter
            if ($record->user_type === 'voter' && $record->voter_id !== null) {
                $voterFullName = $snapshot['_voter_full_name']
                    ?? trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? ''))
                    ?: $record->email;
                Voter::create([
                    'user_id'       => $user->id,
                    'full_name'     => $voterFullName,
                    'email'         => $record->email,
                    'referral_code' => Str::upper(Str::random(8)),
                ]);
            }

            // Restore a bare politician profile if the original was a politician
            if ($record->user_type === 'politician' && $record->politician_id !== null) {
                $politicianName = trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? '')) ?: $record->email;
                Politician::create([
                    'user_id'   => $user->id,
                    'full_name' => $politicianName,
                    'slug'      => Str::slug($politicianName . '-' . Str::random(4)),
                ]);
            }

            // Restore a bare citizen profile if the original was a citizen
            if ($record->user_type === 'citizen' && $record->citizen_id !== null) {
                $citizenFullName = $snapshot['_citizen_full_name']
                    ?? trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? ''))
                    ?: $record->email;
                Citizen::create([
                    'user_id'   => $user->id,
                    'full_name' => $citizenFullName,
                ]);
            }

            // Mark the archive record as restored
            $record->update([
                'restored_user_id'    => $user->id,
                'restored_by_user_id' => $restoredBy->id,
                'restored_at'         => now(),
            ]);

            // Send password reset so user can regain access
            Password::sendResetLink(['email' => $user->email]);

            // Audit log
            AdminSecurityAuditLog::record(
                $restoredBy,
                'user_restored',
                [
                    'deleted_account_id' => $record->id,
                    'original_user_id'   => $record->original_user_id,
                    'new_user_id'        => $user->id,
                    'email'              => $user->email,
                ]
            );

            Log::info('UserDeletionService: account restored', [
                'deleted_account_id' => $record->id,
                'new_user_id'        => $user->id,
                'restored_by'        => $restoredBy->id,
            ]);

            return $user;
        });
    }
}
