<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prevents duplicate charge records for the same Stripe PaymentIntent.
        // IMPORTANT: Run the query below against production before applying and
        // resolve any existing duplicates first:
        //   SELECT stripe_payment_intent_id, COUNT(*) FROM campaign_transactions
        //   GROUP BY 1 HAVING COUNT(*) > 1;
        Schema::table('campaign_transactions', function (Blueprint $table) {
            $table->unique('stripe_payment_intent_id', 'uniq_campaign_tx_stripe_pi');
        });

        // Prevents duplicate credit entries for the same transaction + credit type.
        // IMPORTANT: Run the query below against production before applying:
        //   SELECT related_transaction_id, transaction_type, COUNT(*) FROM politician_credits
        //   WHERE related_transaction_id IS NOT NULL
        //   GROUP BY 1,2 HAVING COUNT(*) > 1;
        Schema::table('politician_credits', function (Blueprint $table) {
            $table->unique(
                ['related_transaction_id', 'transaction_type'],
                'uniq_politician_credits_tx_type'
            );
        });
    }

    public function down(): void
    {
        Schema::table('campaign_transactions', function (Blueprint $table) {
            $table->dropUnique('uniq_campaign_tx_stripe_pi');
        });

        Schema::table('politician_credits', function (Blueprint $table) {
            $table->dropUnique('uniq_politician_credits_tx_type');
        });
    }
};
