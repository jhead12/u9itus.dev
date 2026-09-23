<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Production picked up politician_chatter_moderation_logs mid-CREATE from an
 * earlier failed deploy of 2026_09_22_000001 (columns present, FK constraints
 * added by separate follow-up ALTER statements that never ran). That
 * migration's repair path backfilled the politician_chatter_item_id FK but
 * missed admin_user_id — add it here if it's still missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        // information_schema.TABLE_CONSTRAINTS is MySQL-only; a fresh SQLite
        // test/staging DB already gets this FK inline from 2026_09_22_000001.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $fkExists = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('TABLE_NAME', 'politician_chatter_moderation_logs')
            ->where('CONSTRAINT_NAME', 'politician_chatter_moderation_logs_admin_user_id_foreign')
            ->exists();

        if (! $fkExists) {
            Schema::table('politician_chatter_moderation_logs', function (Blueprint $table) {
                $table->foreign('admin_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        Schema::table('politician_chatter_moderation_logs', function (Blueprint $table) {
            $table->dropForeign(['admin_user_id']);
        });
    }
};
