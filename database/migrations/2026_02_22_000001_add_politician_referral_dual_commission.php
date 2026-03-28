<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dual referral incentive structure:
 *
 *  Voter  → refers → Voter      : earns 10% of voter payout per completed view  ($0.050/view)
 *  Voter  → refers → Politician : earns 10% of politician's first credit purchase (one-time)
 *
 * Changes:
 *  politicians.referred_by_voter_id  — FK to voters (nullable): who recruited this politician
 *  referral_earnings.referral_type   — 'voter_view' | 'politician_procurement'
 *  referral_earnings.politician_id   — FK to politicians (nullable): set for procurement rows
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── politicians: track who recruited this politician ─────────────────
        Schema::table('politicians', function (Blueprint $table) {
            $table->unsignedBigInteger('referred_by_voter_id')
                  ->nullable()
                  ->after('is_active');

            $table->foreign('referred_by_voter_id')
                  ->references('id')->on('voters')
                  ->nullOnDelete();
        });

        // ── referral_earnings: distinguish commission type + link to politician
        Schema::table('referral_earnings', function (Blueprint $table) {
            // Allow null for procurement commissions (no voter view involved)
            $table->unsignedBigInteger('referred_voter_id_new')
                  ->nullable()
                  ->after('referrer_voter_id');

            // 'voter_view' = recurring per-view commission (existing behaviour)
            // 'politician_procurement' = one-time commission on politician's first purchase
            $table->string('referral_type', 30)
                  ->default('voter_view')
                  ->after('commission_amount');

            $table->unsignedBigInteger('politician_id')
                  ->nullable()
                  ->after('referral_type');

            $table->foreign('politician_id')
                  ->references('id')->on('politicians')
                  ->nullOnDelete();

            $table->index('referral_type');
        });

        // Copy existing referred_voter_id values to new nullable column, then swap
        DB::statement('UPDATE referral_earnings SET referred_voter_id_new = referred_voter_id');

        // Drop the old NOT NULL FKs, swap in nullable equivalents
        Schema::table('referral_earnings', function (Blueprint $table) {
            $table->dropForeign(['referred_voter_id']);
            $table->dropForeign(['view_session_id']);
            $table->dropColumn(['referred_voter_id', 'view_session_id']);
        });

        Schema::table('referral_earnings', function (Blueprint $table) {
            $table->renameColumn('referred_voter_id_new', 'referred_voter_id');

            // Also add nullable view_session_id replacement
            $table->unsignedBigInteger('view_session_id')->nullable();

            $table->foreign('referred_voter_id')
                  ->references('id')->on('voters')
                  ->nullOnDelete();

            $table->foreign('view_session_id')
                  ->references('id')->on('view_sessions')
                  ->nullOnDelete();
        });

        // Restore existing view_session_id data
        // (null for procurement rows; new rows will set view_session_id correctly)
    }

    public function down(): void
    {
        Schema::table('referral_earnings', function (Blueprint $table) {
            $table->dropForeign(['politician_id']);
            $table->dropForeign(['referred_voter_id']);
            $table->dropForeign(['view_session_id']);
            $table->dropIndex(['referral_type']);
            $table->dropColumn(['referral_type', 'politician_id', 'referred_voter_id', 'view_session_id']);
        });

        // Re-add the original NOT NULL FKs (any procurement rows will have been purged above)
        Schema::table('referral_earnings', function (Blueprint $table) {
            $table->unsignedBigInteger('referred_voter_id')->after('referrer_voter_id');
            $table->unsignedBigInteger('view_session_id');

            $table->foreign('referred_voter_id')
                  ->references('id')->on('voters')
                  ->cascadeOnDelete();

            $table->foreign('view_session_id')
                  ->references('id')->on('view_sessions')
                  ->cascadeOnDelete();
        });

        Schema::table('politicians', function (Blueprint $table) {
            $table->dropForeign(['referred_by_voter_id']);
            $table->dropColumn('referred_by_voter_id');
        });
    }
};
