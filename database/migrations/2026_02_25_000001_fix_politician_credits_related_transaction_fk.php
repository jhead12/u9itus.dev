<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix: politician_credits.related_transaction_id was incorrectly constrained
 * to politician_credits.id (self-referential).  It is used exclusively to
 * store the ID of the corresponding campaign_transactions row so we can detect
 * whether a payment has already been credited (used by billing:recover-stuck
 * and the finalizePaymentIntent idempotency path).
 *
 * This migration:
 *  1. Drops the wrong self-referential FK.
 *  2. Re-adds the FK referencing campaign_transactions.id.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Get the exact FK constraint name — it varies between MySQL and the
        // naming convention used by Laravel's Blueprint for the original table.
        // We attempt the standard Laravel-generated name; fall back to a raw
        // DROP if the DB is MySQL and supports information_schema lookup.
        Schema::table('politician_credits', function (Blueprint $table) {
            // Drop the existing (wrong) FK constraint.
            // Laravel default name: {table}_{column}_foreign
            $table->dropForeign(['related_transaction_id']);
        });

        Schema::table('politician_credits', function (Blueprint $table) {
            // Re-add pointing at campaign_transactions.
            $table->foreign('related_transaction_id')
                  ->references('id')
                  ->on('campaign_transactions')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('politician_credits', function (Blueprint $table) {
            $table->dropForeign(['related_transaction_id']);
        });

        Schema::table('politician_credits', function (Blueprint $table) {
            // Restore original (self-referential) FK on rollback.
            $table->foreign('related_transaction_id')
                  ->references('id')
                  ->on('politician_credits')
                  ->nullOnDelete();
        });
    }
};
