<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 14 — Campaign Scheduling
 *
 * Adds two nullable timestamp columns to political_campaigns:
 *
 *   scheduled_start_at  When set and in the future, the campaign is approved but
 *                       held in 'scheduled' status until the Artisan command
 *                       campaigns:apply-schedule promotes it to 'active'.
 *
 *   scheduled_end_at    When set, the same command auto-pauses the campaign once
 *                       this timestamp has passed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('political_campaigns', function (Blueprint $table): void {
            $table->timestamp('scheduled_start_at')
                  ->nullable()
                  ->after('completed_at')
                  ->comment('Earliest datetime the campaign should go active (null = immediate)');

            $table->timestamp('scheduled_end_at')
                  ->nullable()
                  ->after('scheduled_start_at')
                  ->comment('Datetime the campaign should auto-pause (null = no expiry)');
        });
    }

    public function down(): void
    {
        Schema::table('political_campaigns', function (Blueprint $table): void {
            $table->dropColumn(['scheduled_start_at', 'scheduled_end_at']);
        });
    }
};
