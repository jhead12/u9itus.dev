<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * citizen_campaigns — parallel to political_campaigns, kept as a separate
     * table (per doc/Creative.md Sprint 7.5) for cleaner audit trails and
     * distinct moderation queues. Same money/status/media/schedule/repeat-view
     * shape as political_campaigns, minus governance_level/district targeting,
     * plus citizen-specific locality + ad-type fields:
     *
     *   citizen_ad_type      local_business|community_notice|ballot_issue|general_announcement
     *   target_zip           single 5-digit zip the campaign is centered on
     *   target_zip_radius    radius in miles around target_zip
     *   pac_registration_id  required only when citizen_ad_type = ballot_issue
     *   daily_view_cap       standard tiers default to a 500-view/day cap;
     *                        ballot_issue campaigns leave this null (uncapped,
     *                        subject to the same admin approval queue as
     *                        political campaigns)
     */
    public function up(): void
    {
        Schema::create('citizen_campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('citizen_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('message_summary')->nullable();
            $table->text('video_blurb')->nullable();
            $table->enum('campaign_type', ['video', 'live_feed', 'q_and_a'])->default('video');
            $table->string('citizen_ad_type');
            $table->string('media_url')->nullable();
            $table->enum('media_type', ['youtube', 'vimeo', 'direct_file', 's3_cloudfront', 'hls_stream'])
                ->default('youtube');
            $table->unsignedInteger('media_duration')->nullable()->comment('seconds');
            $table->string('thumbnail_url')->nullable();
            $table->string('subtitle_url')->nullable()->comment('Optional WebVTT (.vtt) URL for video captions/subtitles');
            $table->string('live_feed_url')->nullable();
            $table->timestamp('live_scheduled_at')->nullable();
            $table->timestamp('live_ended_at')->nullable();
            $table->decimal('revenue_per_view', 8, 2)->default(0.75);
            $table->decimal('voter_payout_per_view', 8, 2)->default(0.50);
            $table->decimal('total_budget', 12, 2)->default(0);
            $table->decimal('amount_spent', 12, 2)->default(0);
            $table->decimal('head_enterprises_fee_percent', 5, 2)->default(15);
            $table->unsignedInteger('total_views_requested')->default(0);
            $table->unsignedInteger('views_completed')->default(0);
            $table->string('target_zip', 5)->nullable();
            $table->unsignedInteger('target_zip_radius')->nullable()->comment('miles');
            $table->unsignedInteger('daily_view_cap')->nullable()->default(500);
            $table->string('pac_registration_id')->nullable();
            $table->unsignedInteger('min_watch_time_percent')->default(100);
            $table->string('status')->default('draft');
            $table->string('approval_status')->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->string('payment_status')->default('pending');
            $table->string('stripe_payment_intent_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('scheduled_start_at')->nullable();
            $table->timestamp('scheduled_end_at')->nullable();
            $table->boolean('allow_repeat_views')->default(false);
            $table->unsignedSmallInteger('repeat_view_cooldown_hours')->default(24);
            $table->unsignedTinyInteger('max_views_per_voter')->default(1);
            $table->timestamps();

            $table->index(['status', 'approval_status']);
            $table->index('citizen_ad_type');
            $table->index('target_zip');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('citizen_campaigns');
    }
};
