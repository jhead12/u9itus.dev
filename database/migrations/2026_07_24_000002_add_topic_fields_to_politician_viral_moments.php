<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tag viral-moment clips (C-SPAN / YouTube / news) with the issue topic they're
 * about, mirroring candidate_news_articles.topic_key. Classification happens at
 * signal-rollup time (IssueClassifierService), not fetch time, so the fetcher
 * services (YouTubeMomentService / CspanMomentService) are unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('politician_viral_moments', function (Blueprint $table) {
            if (! Schema::hasColumn('politician_viral_moments', 'topic_key')) {
                $table->string('topic_key', 100)->nullable()->after('match_confidence');
            }
            if (! Schema::hasColumn('politician_viral_moments', 'topic_confidence')) {
                $table->decimal('topic_confidence', 4, 3)->nullable()->after('topic_key');
            }
        });

        // Topic-scoped recency index, mirroring candidate_news_articles.
        try {
            Schema::table('politician_viral_moments', function (Blueprint $table) {
                $table->index(['topic_key', 'published_at'], 'politician_viral_moments_topic_idx');
            });
        } catch (\Throwable $e) {
            // Index may already exist on re-run; safe to ignore.
        }
    }

    public function down(): void
    {
        try {
            Schema::table('politician_viral_moments', function (Blueprint $table) {
                $table->dropIndex('politician_viral_moments_topic_idx');
            });
        } catch (\Throwable $e) {
            // ignore
        }

        Schema::table('politician_viral_moments', function (Blueprint $table) {
            if (Schema::hasColumn('politician_viral_moments', 'topic_confidence')) {
                $table->dropColumn('topic_confidence');
            }
            if (Schema::hasColumn('politician_viral_moments', 'topic_key')) {
                $table->dropColumn('topic_key');
            }
        });
    }
};