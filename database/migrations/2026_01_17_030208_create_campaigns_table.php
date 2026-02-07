<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('advertiser_id')->constrained('advertisers')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('campaign_type', ['video', 'audio', 'text'])->default('video');
            $table->string('media_file_url')->nullable();
            $table->integer('media_duration')->nullable()->comment('Duration in seconds');
            $table->string('thumbnail_url')->nullable();
            $table->decimal('total_budget', 10, 2);
            $table->decimal('payment_per_view', 10, 2);
            $table->decimal('head_enterprises_fee_percent', 5, 2)->default(15.0);
            $table->integer('total_views_requested');
            $table->integer('views_completed')->default(0);
            $table->json('target_states')->nullable();
            $table->json('target_cities')->nullable();
            $table->integer('max_views_per_viewer')->default(1);
            $table->integer('min_watch_time_percent')->default(80);
            $table->enum('status', ['draft', 'pending_approval', 'active', 'paused', 'completed', 'cancelled'])->default('draft');
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->enum('payment_status', ['pending', 'authorized', 'captured'])->default('pending');
            $table->string('stripe_payment_intent_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
