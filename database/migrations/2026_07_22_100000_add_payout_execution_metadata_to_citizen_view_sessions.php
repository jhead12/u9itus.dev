<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirror the payout-execution metadata added to view_sessions in
     * 2026_04_07_120000 onto citizen_view_sessions, so citizen-campaign
     * voter payouts can flow through the same settlement path (requestPayout
     * → processBatchPayoutsForRun → PayPal reconciliation) as political
     * payouts. Without these columns, citizen earnings accrue in
     * pending_earnings but can never be marked paid.
     */
    public function up(): void
    {
        Schema::table('citizen_view_sessions', function (Blueprint $table) {
            $table->string('processor_selected', 50)->nullable()->after('paid_at');
            $table->string('processor_executed', 50)->nullable()->after('processor_selected');
            $table->string('processor_reference', 255)->nullable()->after('processor_executed');
            $table->decimal('processor_fee', 8, 2)->default(0)->after('processor_reference');

            $table->index(['processor_executed', 'paid_at'], 'citizen_vs_processor_executed_paid_at_idx');
            $table->index('processor_selected', 'citizen_vs_processor_selected_idx');
            $table->index('processor_reference', 'citizen_vs_processor_reference_idx');
        });
    }

    public function down(): void
    {
        Schema::table('citizen_view_sessions', function (Blueprint $table) {
            $table->dropIndex('citizen_vs_processor_executed_paid_at_idx');
            $table->dropIndex('citizen_vs_processor_selected_idx');
            $table->dropIndex('citizen_vs_processor_reference_idx');

            $table->dropColumn([
                'processor_selected',
                'processor_executed',
                'processor_reference',
                'processor_fee',
            ]);
        });
    }
};