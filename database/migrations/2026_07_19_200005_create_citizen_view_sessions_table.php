<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citizen_view_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('citizen_campaign_id')->constrained('citizen_campaigns')->cascadeOnDelete();
            $table->foreignId('voter_id')->constrained('voters')->cascadeOnDelete();
            $table->string('status', 30)->default('assigned');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('watch_time_seconds')->default(0);
            $table->decimal('completion_percentage', 5, 2)->default(0);
            $table->decimal('voter_payout_amount', 10, 2)->default(0);
            $table->decimal('platform_revenue', 10, 2)->default(0);
            $table->decimal('referral_commission', 10, 2)->default(0);
            $table->string('payment_status', 30)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('device_fingerprint', 255)->nullable();
            $table->text('user_agent')->nullable();
            $table->decimal('fraud_score', 5, 2)->default(0);
            $table->json('fraud_flags')->nullable();
            $table->timestamps();

            $table->index(['citizen_campaign_id', 'status']);
            $table->index(['voter_id', 'status']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citizen_view_sessions');
    }
};
