<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * COR-2: add a real foreign key on payout_attempts.voter_id.
 *
 * The create migration defined it as unsignedBigInteger + index with no FK,
 * so a hard-deleted voter left orphaned audit rows. Make the column nullable
 * and constrain it to voters with nullOnDelete (not cascade) — payout_attempts
 * is a financial audit trail and the only consumer (AdminController::show) already
 * guards `if ($user->voter)`. Mirrors the sibling payout_run_skipped_items
 * convention (2026_04_10_100000:29: foreignId('voter_id')->nullable()
 * ->constrained('voters')->nullOnDelete()).
 *
 * Split across two Schema::table closures so the FK is added in its own
 * blueprint pass — the same pattern used by 2026_02_25_000001_fix_politician
 * _credits_related_transaction_fk, which the SQLite test suite exercises cleanly.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guarded: an earlier partial run of this migration (e.g. a non-fatal
        // `migrate --force` on web boot that failed on the FK step below) can
        // leave the index already dropped without the migration being recorded
        // as applied. MySQL DDL auto-commits per statement, so retries must
        // tolerate that half-applied state instead of erroring on re-drop.
        Schema::table('payout_attempts', function (Blueprint $table) {
            if (Schema::hasIndex('payout_attempts', ['voter_id'])) {
                $table->dropIndex(['voter_id']);
            }
            $table->unsignedBigInteger('voter_id')->nullable()->change();
        });

        // Orphaned rows (voter hard-deleted before this FK existed) would
        // otherwise fail the ALTER TABLE below with SQLSTATE[23000] 1452.
        DB::table('payout_attempts')
            ->whereNotNull('voter_id')
            ->whereNotIn('voter_id', DB::table('voters')->select('id'))
            ->update(['voter_id' => null]);

        $hasForeignKey = collect(Schema::getForeignKeys('payout_attempts'))
            ->contains(fn ($fk) => $fk['columns'] === ['voter_id']);

        if (! $hasForeignKey) {
            Schema::table('payout_attempts', function (Blueprint $table) {
                $table->foreign('voter_id')
                      ->references('id')
                      ->on('voters')
                      ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('payout_attempts', function (Blueprint $table) {
            $table->dropForeign(['voter_id']);
        });

        Schema::table('payout_attempts', function (Blueprint $table) {
            $table->unsignedBigInteger('voter_id')->nullable(false)->change();
            if (! Schema::hasIndex('payout_attempts', ['voter_id'])) {
                $table->index('voter_id');
            }
        });
    }
};