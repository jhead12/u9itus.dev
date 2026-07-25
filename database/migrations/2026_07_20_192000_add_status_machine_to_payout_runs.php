<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCH-1: add a status machine to payout_runs so batch payouts can run
 * asynchronously as a queued job without blocking the web request.
 *
 *   pending → running → completed
 *                     ↘ failed
 *
 * Counts (processed_count / skipped_count / total_paid) are incremented
 * inside the loop now, so a partial run leaves accurate counters instead of
 * the previous all-or-nothing end-of-loop write.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payout_runs', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->index()->after('trigger_source');
            $table->timestamp('started_at')->nullable()->after('meta');
            $table->timestamp('completed_at')->nullable()->after('started_at');
            $table->timestamp('failed_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('payout_runs', function (Blueprint $table) {
            $table->dropColumn(['status', 'started_at', 'completed_at', 'failed_at']);
        });
    }
};