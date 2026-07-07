<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voter_saved_articles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('voter_id');
            $table->unsignedBigInteger('candidate_news_article_id');
            $table->timestamp('saved_at')->useCurrent();

            $table->unique(['voter_id', 'candidate_news_article_id']);
            $table->index('voter_id');

            $table->foreign('voter_id')
                  ->references('id')->on('voters')
                  ->cascadeOnDelete();

            $table->foreign('candidate_news_article_id')
                  ->references('id')->on('candidate_news_articles')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voter_saved_articles');
    }
};
