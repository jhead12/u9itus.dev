<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Only an "email_" column is added — there is no in-app/push/SMS delivery
 * path for the saved-places digest yet, so adding unused columns for those
 * channels (as the rest of this table's fields do) would be misleading.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->boolean('email_boundary_digest')->default(false)->after('email_system_announcements');
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn('email_boundary_digest');
        });
    }
};
