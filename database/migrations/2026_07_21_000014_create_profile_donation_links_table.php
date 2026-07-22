<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * profile_donation_links
 *
 * Donation page URLs scraped from a politician's own official or campaign site.
 *
 * SAFETY: link-out only. This table stores the donate page URL and a platform
 * hint — it deliberately has NO amount, frequency, or form-html columns and the
 * service never proxies, embeds, or intermediates a donation flow. The
 * presentation layer renders each row as a plain outbound link.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Self-heal: skip if a prior partial batch already created the table
        // (see profile_enrichment_runs migration for the MySQL DDL rationale).
        if (Schema::hasTable('profile_donation_links')) {
            return;
        }

        Schema::create('profile_donation_links', function (Blueprint $table) {
            $table->id();

            $table->morphs('profilable');

            $table->foreignId('run_id')
                ->nullable()
                ->constrained('profile_enrichment_runs')
                ->nullOnDelete();

            $table->string('url', 2048);

            $table->enum('platform', [
                'actblue',
                'winred',
                'anedp',
                'democracy_engine',
                'donorbox',
                'givebutter',
                'stripe',
                'paypal',
                'square',
                'candidate_site',
                'party_committee',
                'other',
            ]);

            $table->boolean('is_official')->default(false); // linked from the candidate's own site
            $table->string('label', 128)->nullable(); // anchor text, e.g. "Donate"

            $table->string('source_url', 2048);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->unique(['profilable_type', 'profilable_id', 'url'], 'profile_donation_links_unique');
            $table->index(['profilable_type', 'profilable_id', 'platform'], 'profile_donation_links_platform_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_donation_links');
    }
};