<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * viral_moment_enrichment_runs
 *
 * Per-run provenance for the viral-moment enricher (the YouTube Data API /
 * C-SPAN / news-feed pass that fills politician_viral_moments). One row per
 * fetch against an external source for a politician — records when it ran,
 * which source was queried, the search query used, and whether it succeeded,
 * was rate-limited, or blew the quota.
 *
 * Mirrors profile_enrichment_runs: the fact rows (politician_viral_moments)
 * each carry their own source + score so a moment can stand alone without
 * joining back to a run, and run_id on each fact is nullable so manually-seeded
 * moments don't need a synthetic run. Used for staleness gating
 * (ViralMomentEnricherService skips politicians whose latest run is within
 * --stale-hours), the same way profile_enrichment_runs drives enrich-profiles.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Self-heal: skip if a prior partial batch already created the table
        // (see profile_enrichment_runs migration for the MySQL DDL rationale).
        if (Schema::hasTable('viral_moment_enrichment_runs')) {
            return;
        }

        Schema::create('viral_moment_enrichment_runs', function (Blueprint $table) {
            $table->id();

            // Moments are politician-scoped (not morphed like profile_enrichment_runs,
            // which also covers voters) — keep the FK direct, matching politician_endorsements.
            $table->foreignId('politician_id')->constrained()->cascadeOnDelete();

            // youtube | cspan | news | tiktok | instagram | x  (which source this run queried)
            $table->enum('source', ['youtube', 'cspan', 'news', 'tiktok', 'instagram', 'x']);

            // ok | http_error | rate_limited | quota_exceeded | empty | failed
            $table->enum('fetch_status', [
                'ok',
                'http_error',
                'rate_limited',
                'quota_exceeded',
                'empty',
                'failed',
            ])->default('ok');

            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('query_string', 512)->nullable(); // the search query submitted to the source

            // {moments, featured, kept, dropped} — counters for the run, like extracted_counts
            $table->json('extracted_counts')->nullable();

            $table->timestamp('enriched_at')->nullable();
            $table->timestamps();

            $table->index(['politician_id', 'source', 'enriched_at'], 'viral_moment_runs_owner_when');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viral_moment_enrichment_runs');
    }
};