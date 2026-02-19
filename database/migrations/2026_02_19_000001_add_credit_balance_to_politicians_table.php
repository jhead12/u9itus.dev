<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add a denormalized credit_balance column to the politicians table.
 *
 * The politician_credits table already tracks credit ledger entries
 * (each row stores balance_after for auditability), but the politicians
 * profile has no single readable field for "current credit balance".
 *
 * This column is the authoritative running total and is:
 *  - Incremented when a politician purchases credits.
 *  - Decremented when credit is consumed by a campaign view.
 *  - Backfilled from the latest politician_credits.balance_after on deployment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('politicians', function (Blueprint $table) {
            $table->decimal('credit_balance', 12, 2)
                ->default(0)
                ->after('total_spent')
                ->comment('Current credit balance (denormalized from politician_credits ledger)');
        });

        // Backfill: set credit_balance to the latest balance_after from the ledger.
        // If no ledger rows exist for a politician, balance remains 0.
        DB::statement("
            UPDATE politicians p
            JOIN (
                SELECT politician_id, balance_after
                FROM politician_credits pc1
                WHERE created_at = (
                    SELECT MAX(created_at)
                    FROM politician_credits pc2
                    WHERE pc2.politician_id = pc1.politician_id
                )
            ) latest ON latest.politician_id = p.id
            SET p.credit_balance = latest.balance_after
        ");
    }

    public function down(): void
    {
        Schema::table('politicians', function (Blueprint $table) {
            $table->dropColumn('credit_balance');
        });
    }
};
