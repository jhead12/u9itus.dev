<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deleted_accounts', function (Blueprint $table) {
            // Frozen snapshot of refunds owed + Stripe objects to clean up,
            // captured before the politician/citizen/voter rows (and their
            // cascade-deleted credit/payment-method rows) are removed.
            $table->json('stripe_cleanup_plan')->nullable()->after('user_snapshot');

            // pending -> processing -> completed | partially_completed | failed | not_applicable
            $table->string('stripe_cleanup_status')->default('not_applicable')->after('stripe_cleanup_plan');
            $table->timestamp('stripe_cleanup_started_at')->nullable()->after('stripe_cleanup_status');
            $table->timestamp('stripe_cleanup_completed_at')->nullable()->after('stripe_cleanup_started_at');
            $table->text('stripe_cleanup_error')->nullable()->after('stripe_cleanup_completed_at');

            $table->index('stripe_cleanup_status');
        });
    }

    public function down(): void
    {
        Schema::table('deleted_accounts', function (Blueprint $table) {
            $table->dropIndex(['stripe_cleanup_status']);
            $table->dropColumn([
                'stripe_cleanup_plan',
                'stripe_cleanup_status',
                'stripe_cleanup_started_at',
                'stripe_cleanup_completed_at',
                'stripe_cleanup_error',
            ]);
        });
    }
};
