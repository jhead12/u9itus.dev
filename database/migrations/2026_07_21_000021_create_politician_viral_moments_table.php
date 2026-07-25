<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * politician_viral_moments
 *
 * One row per (politician, source, source_id) external clip/moment — a C-SPAN
 * clip, a YouTube video, or a news-feed video link — with the raw engagement
 * counts and a computed moment_score. App\Services\MomentScoreService computes
 * the score from the stored components (log(views) × velocity × recency_decay ×
 * authority_weight × match_confidence), so the formula can be retuned later
 * without re-scraping: the components live in score_components JSON and the
 * score is re-derived.
 *
 * is_featured marks the single highest-scoring eligible moment per politician
 * (last ~30 days) that surfaces on the profile + map; the denormalized copy on
 * politicians (featured_moment_*) is written from whichever row is featured so
 * the map pin reads one column instead of joining + ranking. Deduped on
 * (politician_id, source, source_id) so re-runs upsert engagement counts
 * instead of duplicating rows — repeat fetches refresh view_count/velocity,
 * strengthening the signal rather than creating noise.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Self-heal: skip if a prior partial batch already created the table
        // (see profile_enrichment_runs migration for the MySQL DDL rationale).
        if (Schema::hasTable('politician_viral_moments')) {
            return;
        }

        Schema::create('politician_viral_moments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('politician_id')->constrained()->cascadeOnDelete();

            $table->foreignId('run_id')
                ->nullable()
                ->constrained('viral_moment_enrichment_runs')
                ->nullOnDelete();

            // youtube | cspan | news | tiktok | instagram | x
            $table->enum('source', ['youtube', 'cspan', 'news', 'tiktok', 'instagram', 'x']);

            // External id on the source (YouTube video id, C-SPAN clip id, etc.) — upsert key.
            $table->string('source_id', 128);

            $table->string('title', 512);
            $table->string('url', 2048);
            $table->string('thumbnail_url', 2048)->nullable();

            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            // Engagement — unsignedBigInteger because viral clips run into 8+ digits.
            $table->unsignedBigInteger('view_count')->nullable();
            $table->unsignedBigInteger('like_count')->nullable();
            $table->unsignedBigInteger('comment_count')->nullable();

            // views / days_since_published — precomputed so scoring is a pure read.
            $table->decimal('view_velocity', 12, 2)->nullable();

            // Source-authority weight (cspan=1.00, news=0.80, youtube=0.60, ...) — tunable.
            $table->decimal('authority_weight', 3, 2)->default(0.60);
            // Name+office+state match confidence 0.00–1.00 from the fetcher.
            $table->decimal('match_confidence', 3, 2)->default(1.00);

            // Computed by MomentScoreService from the components below.
            $table->decimal('moment_score', 8, 4)->nullable();
            // {log_views, velocity, recency_decay, authority, confidence} — audit + retune.
            $table->json('score_components')->nullable();

            $table->boolean('is_featured')->default(false);

            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            // Upsert key — refresh engagement on re-fetch instead of duplicating.
            $table->unique(['politician_id', 'source', 'source_id'], 'politician_viral_moments_unique');
            // Top-moment lookup: featured first, then by score, per politician.
            $table->index(['politician_id', 'is_featured', 'moment_score'], 'politician_viral_moments_top_idx');
            $table->index(['politician_id', 'published_at'], 'politician_viral_moments_recent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('politician_viral_moments');
    }
};