<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * politician_credits.related_transaction_id and citizen_credits.related_transaction_id
 * were created pointing at their own table (self-referencing FK), but every write
 * (CampaignBillingService/CitizenBillingService::addCredits) stores the *_transactions
 * row id there instead. On politician_credits this went unnoticed because that table
 * already has far more rows than campaign_transactions, so the numeric ids happened to
 * coincidentally satisfy the wrong constraint. On the still-empty citizen_credits table
 * it fails outright on the very first insert, since no row exists yet to satisfy it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('politician_credits', function (Blueprint $table) {
            $table->dropForeign(['related_transaction_id']);
        });
        Schema::table('politician_credits', function (Blueprint $table) {
            $table->foreign('related_transaction_id')
                ->references('id')->on('campaign_transactions')
                ->nullOnDelete();
        });

        Schema::table('citizen_credits', function (Blueprint $table) {
            $table->dropForeign(['related_transaction_id']);
        });
        Schema::table('citizen_credits', function (Blueprint $table) {
            $table->foreign('related_transaction_id')
                ->references('id')->on('citizen_transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('politician_credits', function (Blueprint $table) {
            $table->dropForeign(['related_transaction_id']);
        });
        Schema::table('politician_credits', function (Blueprint $table) {
            $table->foreign('related_transaction_id')
                ->references('id')->on('politician_credits')
                ->nullOnDelete();
        });

        Schema::table('citizen_credits', function (Blueprint $table) {
            $table->dropForeign(['related_transaction_id']);
        });
        Schema::table('citizen_credits', function (Blueprint $table) {
            $table->foreign('related_transaction_id')
                ->references('id')->on('citizen_credits')
                ->nullOnDelete();
        });
    }
};
