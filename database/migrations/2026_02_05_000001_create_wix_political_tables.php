<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wix_sites', function (Blueprint $table) {
            $table->id();
            $table->string('instance_id')->unique()->comment('Wix instance ID');
            $table->string('site_url')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('site_display_name')->nullable();
            $table->string('owner_email')->nullable();
            $table->string('plan_type')->default('free');
            $table->boolean('is_active')->default(true);
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('uninstalled_at')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('politicians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wix_site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('wix_member_id')->nullable();
            $table->uuid('uuid')->unique();
            $table->string('full_name');
            $table->string('political_office')->nullable();
            $table->string('governance_level')->nullable();
            $table->string('district')->nullable();
            $table->string('party_affiliation')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('website_url')->nullable();
            $table->text('bio')->nullable();
            $table->string('profile_photo_url')->nullable();
            $table->boolean('verified_official')->default(false);
            $table->string('kyc_status')->default('pending');
            $table->string('stripe_customer_id')->nullable();
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->unsignedInteger('total_campaigns')->default(0);
            $table->unsignedInteger('total_views_received')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['governance_level', 'state']);
            $table->index('wix_member_id');
        });

        Schema::create('voters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wix_site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('wix_member_id')->nullable();
            $table->uuid('uuid')->unique();
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('congressional_district')->nullable();
            $table->json('preferred_governance_levels')->nullable();
            $table->foreignId('referred_by_voter_id')->nullable()->constrained('voters')->nullOnDelete();
            $table->string('referral_code', 16)->unique();
            $table->string('payment_method')->default('wallet');
            $table->string('paypal_email')->nullable();
            $table->string('cashapp_tag')->nullable();
            $table->decimal('wallet_balance', 12, 2)->default(0);
            $table->decimal('total_earned', 12, 2)->default(0);
            $table->decimal('pending_earnings', 12, 2)->default(0);
            $table->unsignedInteger('total_views')->default(0);
            $table->decimal('trust_score', 5, 2)->default(100);
            $table->text('device_fingerprint')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('flagged_for_fraud')->default(false);
            $table->timestamp('last_view_at')->nullable();
            $table->timestamps();

            $table->index(['state', 'city']);
            $table->index('referral_code');
            $table->index('wix_member_id');
        });

        Schema::create('political_campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('politician_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('message_summary')->nullable();
            $table->enum('campaign_type', ['video', 'live_feed'])->default('video');
            $table->string('governance_level')->nullable();
            $table->string('media_url')->nullable();
            $table->unsignedInteger('media_duration')->nullable()->comment('seconds');
            $table->string('thumbnail_url')->nullable();
            $table->string('live_feed_url')->nullable();
            $table->timestamp('live_scheduled_at')->nullable();
            $table->timestamp('live_ended_at')->nullable();
            $table->decimal('revenue_per_view', 8, 2)->default(0.60);
            $table->decimal('voter_payout_per_view', 8, 2)->default(0.25);
            $table->decimal('total_budget', 12, 2)->default(0);
            $table->decimal('amount_spent', 12, 2)->default(0);
            $table->decimal('head_enterprises_fee_percent', 5, 2)->default(15);
            $table->unsignedInteger('total_views_requested')->default(0);
            $table->unsignedInteger('views_completed')->default(0);
            $table->json('target_states')->nullable();
            $table->json('target_cities')->nullable();
            $table->json('target_districts')->nullable();
            $table->json('target_governance_levels')->nullable();
            $table->unsignedInteger('min_watch_time_percent')->default(100);
            $table->string('status')->default('draft');
            $table->string('approval_status')->default('pending');
            $table->string('payment_status')->default('pending');
            $table->string('stripe_payment_intent_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'approval_status']);
            $table->index('governance_level');
        });

        Schema::create('view_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('political_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voter_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('assigned');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('watch_time_seconds')->default(0);
            $table->decimal('completion_percentage', 5, 2)->default(0);
            $table->decimal('voter_payout_amount', 8, 2)->default(0);
            $table->decimal('platform_revenue', 8, 2)->default(0);
            $table->decimal('referral_commission', 8, 2)->default(0);
            $table->string('payment_status')->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('device_fingerprint')->nullable();
            $table->text('user_agent')->nullable();
            $table->decimal('fraud_score', 5, 2)->default(0);
            $table->json('fraud_flags')->nullable();
            $table->timestamps();

            $table->index(['voter_id', 'status']);
            $table->index(['political_campaign_id', 'status']);
            $table->index('payment_status');
        });

        Schema::create('referral_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_voter_id')->constrained('voters')->cascadeOnDelete();
            $table->foreignId('referred_voter_id')->constrained('voters')->cascadeOnDelete();
            $table->foreignId('view_session_id')->constrained()->cascadeOnDelete();
            $table->decimal('commission_amount', 8, 2);
            $table->boolean('paid')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('referrer_voter_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_earnings');
        Schema::dropIfExists('view_sessions');
        Schema::dropIfExists('political_campaigns');
        Schema::dropIfExists('voters');
        Schema::dropIfExists('politicians');
        Schema::dropIfExists('wix_sites');
    }
};
