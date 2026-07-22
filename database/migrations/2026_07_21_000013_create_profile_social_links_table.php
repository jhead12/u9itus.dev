<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * profile_social_links
 *
 * Social / newsletter profile links scraped from a politician's own official
 * or campaign website. substack|rss|newsletter cover the newsletter adapter's
 * source link — when a Substack/newsletter URL is found here, the service pulls
 * recent public posts (titled links only) into candidate_news_articles.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Self-heal: skip if a prior partial batch already created the table
        // (see profile_enrichment_runs migration for the MySQL DDL rationale).
        if (Schema::hasTable('profile_social_links')) {
            return;
        }

        Schema::create('profile_social_links', function (Blueprint $table) {
            $table->id();

            $table->morphs('profilable');

            $table->foreignId('run_id')
                ->nullable()
                ->constrained('profile_enrichment_runs')
                ->nullOnDelete();

            $table->enum('platform', [
                'facebook',
                'x_twitter',
                'instagram',
                'youtube',
                'tiktok',
                'linkedin',
                'threads',
                'bluesky',
                'mastodon',
                'truth_social',
                'substack',
                'rss',
                'newsletter',
                'website',
                'other',
            ]);

            $table->string('url', 2048);
            $table->string('handle', 255)->nullable();
            $table->string('display_name', 255)->nullable(); // publication name for substack/newsletter

            $table->boolean('is_official')->default(false); // linked from the candidate's own site

            $table->string('source_url', 2048); // page where this link was discovered
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->unique(['profilable_type', 'profilable_id', 'platform', 'url'], 'profile_social_links_unique');
            $table->index(['profilable_type', 'profilable_id', 'platform'], 'profile_social_links_platform_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_social_links');
    }
};