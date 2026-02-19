<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidate user_type to the standalone political platform roles:
 *   admin | politician | voter
 *
 * Legacy Dial4Dough roles that need remapping:
 *   advertiser  → politician  (both are the campaign/paying side)
 *   viewer      → voter       (both are the watching/rewarded side)
 *
 * On MySQL the ENUM is altered to drop the legacy values.
 * On SQLite (dev/test) the column is unconstrained TEXT, so only the
 * data values are updated — no DDL change required.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Step 1: Remap legacy values ───────────────────────────────────────
        DB::table('users')
            ->where('user_type', 'advertiser')
            ->update(['user_type' => 'politician']);

        DB::table('users')
            ->where('user_type', 'viewer')
            ->update(['user_type' => 'voter']);

        // ── Step 2: Tighten the MySQL ENUM ────────────────────────────────────
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE users
                MODIFY COLUMN user_type
                ENUM('admin','politician','voter')
                NOT NULL DEFAULT 'voter'
            ");
        } else {
            // SQLite — enforce at application level; no DDL needed
            Schema::table('users', function (Blueprint $table) {
                $table->string('user_type')->default('voter')->change();
            });
        }
    }

    public function down(): void
    {
        // Restore MySQL ENUM to include legacy values (data remap is irreversible)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE users
                MODIFY COLUMN user_type
                ENUM('advertiser','viewer','admin','voter','politician')
                NOT NULL DEFAULT 'voter'
            ");
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->string('user_type')->default('voter')->change();
            });
        }
    }
};
