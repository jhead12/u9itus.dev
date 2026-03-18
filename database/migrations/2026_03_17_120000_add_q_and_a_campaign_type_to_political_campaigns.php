<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('political_campaigns')) {
            return;
        }

        // MySQL enum alteration for existing production databases.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE political_campaigns MODIFY campaign_type ENUM('video','live_feed','q_and_a') NOT NULL DEFAULT 'video'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('political_campaigns')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE political_campaigns MODIFY campaign_type ENUM('video','live_feed') NOT NULL DEFAULT 'video'");
        }
    }
};
