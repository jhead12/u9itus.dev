<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Production data-fix: repair voter rows that have a NULL primary key
 * or a NULL user_id caused by a registration race condition.
 *
 * Problem:
 *  - In MySQL, if voter.id is NULL (AUTO_INCREMENT failure), Eloquent cannot
 *    manage the row at all.
 *  - If voter.user_id is NULL, the User->voter() hasOne relationship returns
 *    null and the dashboard cannot load the profile.
 *
 * Fix strategy:
 *  1. Delete any voter rows whose id IS NULL but whose email already has
 *     another voter row WITH a valid id (true duplicates).
 *  2. For voter rows whose id IS NULL and no duplicate exists, re-insert
 *     them with the next auto-increment value so they become Eloquent-safe.
 *  3. For voter rows whose user_id IS NULL, match the voter to a user by
 *     email and stitch the user_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Step 1: fix rows with a NULL primary key ─────────────────────────
        // SQLite supports integer pk = null as a special rowid case, but MySQL
        // AUTO_INCREMENT should never produce NULL.  Guard both drivers.

        $nullIdVoters = DB::table('voters')->whereNull('id')->get();

        foreach ($nullIdVoters as $row) {
            // Check whether a proper row already exists for the same email
            $existing = $row->email
                ? DB::table('voters')
                    ->whereNotNull('id')
                    ->where('email', $row->email)
                    ->first()
                : null;

            if ($existing) {
                // Duplicate — just remove the broken NULL-id row
                DB::table('voters')
                    ->whereNull('id')
                    ->where('uuid', $row->uuid)
                    ->delete();
            } else {
                // Re-insert the row without specifying id so the DB assigns one
                $attributes = (array) $row;
                unset($attributes['id']);          // let DB assign auto-increment

                // Ensure we keep a unique uuid and referral_code
                if (empty($attributes['uuid'])) {
                    $attributes['uuid'] = (string) Str::uuid();
                }
                if (empty($attributes['referral_code'])) {
                    $attributes['referral_code'] = strtoupper(Str::random(8));
                }

                // Delete the broken row first (uuid unique constraint)
                DB::table('voters')
                    ->whereNull('id')
                    ->where('uuid', $row->uuid)
                    ->delete();

                DB::table('voters')->insert($attributes);
            }
        }

        // ── Step 2: fix rows whose user_id is NULL ───────────────────────────
        // Match orphaned voter rows to users by email and restore the FK.

        $orphans = DB::table('voters')
            ->whereNull('user_id')
            ->whereNotNull('email')
            ->get();

        foreach ($orphans as $voter) {
            $user = DB::table('users')
                ->where('email', $voter->email)
                ->first();

            if ($user) {
                DB::table('voters')
                    ->where('id', $voter->id)
                    ->update(['user_id' => $user->id]);
            }
        }
    }

    public function down(): void
    {
        // This migration repairs data and is not reversible.
    }
};
