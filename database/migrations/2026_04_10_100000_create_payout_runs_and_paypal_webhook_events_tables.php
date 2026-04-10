<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('triggered_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('trigger_source', 50)->default('system');
            $table->decimal('min_payout_amount', 8, 2)->default(5.00);
            $table->unsignedSmallInteger('fraud_hold_hours')->default(48);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->decimal('total_paid', 12, 2)->default(0.00);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('created_at', 'payout_runs_created_at_idx');
        });

        Schema::create('payout_run_skipped_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_run_id')->constrained('payout_runs')->cascadeOnDelete();
            $table->foreignId('voter_id')->nullable()->constrained('voters')->nullOnDelete();
            $table->foreignId('view_session_id')->nullable()->constrained('view_sessions')->nullOnDelete();
            $table->string('reason_bucket', 64);
            $table->decimal('amount', 8, 2)->default(0.00);
            $table->string('processor_selected', 50)->nullable();
            $table->string('processor_executed', 50)->nullable();
            $table->text('reason_detail')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('force_paid_at')->nullable();
            $table->foreignId('force_paid_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('force_pay_reason')->nullable();
            $table->timestamps();

            $table->index(['payout_run_id', 'reason_bucket'], 'payout_skipped_run_reason_idx');
            $table->index('created_at', 'payout_skipped_created_at_idx');
            $table->index('force_paid_at', 'payout_skipped_force_paid_at_idx');
        });

        Schema::create('paypal_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('paypal_event_id', 128)->unique();
            $table->string('event_type', 120);
            $table->string('resource_reference', 255)->nullable();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('resource_reference', 'paypal_webhook_resource_reference_idx');
            $table->index('event_type', 'paypal_webhook_event_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paypal_webhook_events');
        Schema::dropIfExists('payout_run_skipped_items');
        Schema::dropIfExists('payout_runs');
    }
};
