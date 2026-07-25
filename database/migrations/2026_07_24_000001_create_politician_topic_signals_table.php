<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * politician_topic_signals
 *
 * Per-politician × topic evidence rollup that drives inferred issue badges.
 * One row per (politician_id, topic_id). Fed by three sources — news articles
 * (already topic-tagged in candidate_news_articles.topic_key), viral-moment
 * clip titles, and Vote Smart NPAT issue positions — aggregated by
 * App\Services\PoliticianTopicSignalService during the nightly
 * politicians:enrich-issue-badges run.
 *
 * When total_score crosses config('u9itus.issues.signal_threshold'),
 * BadgeService::grantInferredBadges() grants a profile_badges row of
 * badge_type='inferred_discourse'. The signal table is the evidence; the
 * badge is the surfaced label.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('politician_topic_signals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('politician_id')
                ->constrained('politicians')
                ->cascadeOnDelete();

            $table->foreignId('topic_id')
                ->constrained('politician_topics')
                ->cascadeOnDelete();

            // Raw mention counts per source.
            $table->unsignedInteger('news_count')->default(0);
            $table->unsignedInteger('viral_moment_count')->default(0);
            $table->unsignedInteger('votesmart_count')->default(0);

            // Weighted + recency-decayed score — the figure the badge threshold
            // compares against.
            $table->decimal('total_score', 8, 4)->default(0);

            // Breakdown mirroring politician_viral_moments.score_components.
            $table->json('score_components')->nullable();

            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            // One signal row per politician × topic.
            $table->unique(['politician_id', 'topic_id'], 'politician_topic_signals_unique');
            // Browse filter: politicians strongest in a topic.
            $table->index(['topic_id', 'total_score'], 'politician_topic_signals_topic_score_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('politician_topic_signals');
    }
};