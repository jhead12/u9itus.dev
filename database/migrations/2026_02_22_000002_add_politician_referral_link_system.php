<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Politician referral link system.
 *
 * Allows politicians to share their own referral links to recruit:
 *   - New voters  → politician earns 10% of voter payout per completed view (recurring)
 *   - New politicians → politician earns 10% of recruit's first credit purchase (one-time)
 *
 * Schema changes:
 *   politicians.referral_code               — unique 8-char code (generated on boot)
 *   politicians.referred_by_politician_id   — FK: which politician recruited this politician
 *   voters.referred_by_politician_id        — FK: which politician recruited this voter
 *   referral_earnings.referrer_politician_id— FK: politician who earned the commission
 *   referral_earnings.referrer_voter_id     — made nullable (politicians are now also referrers)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── politicians: add referral_code + self-referral tracking ─────────
        Schema::table('politicians', function (Blueprint $table) {
            $table->string('referral_code', 10)
                  ->nullable()
                  ->unique()
                  ->after('uuid');

            $table->unsignedBigInteger('referred_by_politician_id')
                  ->nullable()
                  ->after('referred_by_voter_id');

            $table->foreign('referred_by_politician_id')
                  ->references('id')->on('politicians')
                  ->nullOnDelete();

            // Earnings wallet for when a politician earns referral commissions
            $table->decimal('pending_earnings', 10, 4)
                  ->default(0)
                  ->after('credit_balance');
        });

        // ── voters: track which politician recruited this voter ──────────────
        Schema::table('voters', function (Blueprint $table) {
            $table->unsignedBigInteger('referred_by_politician_id')
                  ->nullable()
                  ->after('referred_by_voter_id');

            $table->foreign('referred_by_politician_id')
                  ->references('id')->on('politicians')
                  ->nullOnDelete();
        });

        // ── referral_earnings: support politician as referrer ────────────────
        Schema::table('referral_earnings', function (Blueprint $table) {
            // Add politician referrer FK column
            $table->unsignedBigInteger('referrer_politician_id')
                  ->nullable()
                  ->after('referrer_voter_id');

            $table->foreign('referrer_politician_id')
                  ->references('id')->on('politicians')
                  ->nullOnDelete();

            $table->index('referrer_politician_id');
        });

        // SQLite cannot ALTER COLUMN directly; use a workaround to make
        // referrer_voter_id nullable (drop FK, recreate nullable column).
        // For MySQL, a direct change() call is fine and SQLite uses the
        // recreate approach below.
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite: recreate referral_earnings with referrer_voter_id nullable
            // Step 1: Create temp column
            Schema::table('referral_earnings', function (Blueprint $table) {
                $table->unsignedBigInteger('referrer_voter_id_new')
                      ->nullable()
                      ->after('referrer_voter_id');
            });

            // Step 2: Copy data
            DB::statement('UPDATE referral_earnings SET referrer_voter_id_new = referrer_voter_id');

            // Step 3: Drop old FK + column
            Schema::table('referral_earnings', function (Blueprint $table) {
                $table->dropForeign(['referrer_voter_id']);
                $table->dropIndex('referral_earnings_referrer_voter_id_index');
                $table->dropColumn('referrer_voter_id');
            });

            // Step 4: Rename new column
            Schema::table('referral_earnings', function (Blueprint $table) {
                $table->renameColumn('referrer_voter_id_new', 'referrer_voter_id');
            });

            // Step 5: Re-add FK + index
            Schema::table('referral_earnings', function (Blueprint $table) {
                $table->foreign('referrer_voter_id')
                      ->references('id')->on('voters')
                      ->nullOnDelete();

                $table->index('referrer_voter_id');
            });
        } else {
            // MySQL / PostgreSQL
            Schema::table('referral_earnings', function (Blueprint $table) {
                $table->unsignedBigInteger('referrer_voter_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('referral_earnings', function (Blueprint $table) {
            $table->dropForeign(['referrer_politician_id']);
            $table->dropIndex('referral_earnings_referrer_politician_id_index');
            $table->dropColumn('referrer_politician_id');
        });

        Schema::table('voters', function (Blueprint $table) {
            $table->dropForeign(['referred_by_politician_id']);
            $table->dropColumn('referred_by_politician_id');
        });

        Schema::table('politicians', function (Blueprint $table) {
            $table->dropForeign(['referred_by_politician_id']);
            $table->dropColumn('referred_by_politician_id');
            $table->dropColumn('referral_code');
            $table->dropColumn('pending_earnings');
        });
    }
};
