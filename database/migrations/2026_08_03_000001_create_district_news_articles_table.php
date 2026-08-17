<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('district_news_articles', function (Blueprint $table) {
            $table->id();

            // e.g. "TX-12", "VT-AL" — the search scope, not a candidate.
            $table->string('district_code', 10)->index();
            $table->string('state', 2)->index();

            // Which city/locality name (or state-level fallback) the article
            // matched on — useful for debugging/admin, not shown to voters.
            $table->string('matched_locality', 120)->nullable();

            // Article content
            $table->string('headline', 512);
            $table->string('source_name', 128)->nullable();
            $table->string('source_url', 2048);
            $table->text('snippet')->nullable();
            $table->timestamp('published_at')->nullable()->index();

            // Source tracking
            $table->string('provider', 32)->default('google_rss');
            $table->string('source_hash', 64)->unique();

            // Boolean locality+keyword relevance gate — not the fuzzy
            // name-matching candidate_news_articles needs, so no confidence
            // score columns here.
            $table->string('verification_status', 24)->default('verified');
            $table->string('verification_reason', 255)->nullable();

            $table->string('topic_key', 100)->nullable();

            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();

            $table->index(['district_code', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('district_news_articles');
    }
};
