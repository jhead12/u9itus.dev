<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * profile_enrichment_runs
 *
 * Per-run provenance for the ProfileEnricherService. One row per fetch of a
 * politician's official/campaign website — records when it ran, what it found,
 * whether robots.txt allowed it, and whether the Claude fallback tier fired.
 *
 * The fact rows themselves (contact methods, addresses, social/donation links)
 * live in their own tables and each carry their own source_url + is_verified so
 * a fact can stand alone in legal scoping without joining back to a run.
 * run_id on each fact row is nullable so manually-seeded facts don't need a
 * synthetic run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_enrichment_runs', function (Blueprint $table) {
            $table->id();

            // Polymorphic owner — Politician today, Voter/Citizen later.
            $table->morphs('profilable');

            // The page that was fetched for this run.
            $table->string('source_url', 2048);

            // ok | http_error | robots_blocked | empty | claude_fallback | failed
            $table->enum('fetch_status', [
                'ok',
                'http_error',
                'robots_blocked',
                'empty',
                'claude_fallback',
                'failed',
            ])->default('ok');

            $table->unsignedSmallInteger('http_status')->nullable();
            $table->boolean('robots_allowed')->default(true);

            // {contact_methods, addresses, social_links, donation_links, newsletter_posts}
            $table->json('extracted_counts')->nullable();

            $table->boolean('used_claude_fallback')->default(false);

            $table->timestamp('enriched_at')->nullable();
            $table->timestamps();

            $table->index(['profilable_type', 'profilable_id', 'enriched_at'], 'profile_enrichment_runs_owner_when');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_enrichment_runs');
    }
};