<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_news_articles', function (Blueprint $table) {
            $table->id();

            // Nullable — articles can be tied to an unregistered candidate by name
            $table->unsignedBigInteger('politician_id')->nullable()->index();
            $table->foreign('politician_id')->references('id')->on('politicians')->nullOnDelete();

            // Candidate name used as the search query (for unregistered candidates)
            $table->string('candidate_name', 255)->index();

            // Article content
            $table->string('headline', 512);
            $table->string('source_name', 128)->nullable();
            $table->string('source_url', 2048);
            $table->text('snippet')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->timestamp('published_at')->nullable()->index();

            // Source tracking
            $table->string('provider', 32)->default('google_rss'); // google_rss | newsapi | gnews
            $table->string('source_hash', 64)->unique(); // SHA256 of source_url to prevent duplicates

            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();

            $table->index(['candidate_name', 'published_at']);
            $table->index(['politician_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_news_articles');
    }
};
