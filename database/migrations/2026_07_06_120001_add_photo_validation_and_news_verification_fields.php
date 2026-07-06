<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('politicians', function (Blueprint $table) {
            $table->string('profile_photo_status', 24)->default('unvalidated')->after('profile_photo_url');
            $table->decimal('profile_photo_validation_confidence', 4, 3)->nullable()->after('profile_photo_status');
            $table->timestamp('profile_photo_last_validated_at')->nullable()->after('profile_photo_validation_confidence');
        });

        Schema::create('politician_photo_quarantines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('politician_id')->constrained('politicians')->cascadeOnDelete();
            $table->string('photo_url', 2048);
            $table->string('status', 24)->default('pending'); // pending|approved|rejected|auto_cleared
            $table->string('validator', 32)->nullable(); // heuristic|anthropic
            $table->decimal('confidence', 4, 3)->nullable();
            $table->string('reason', 255)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->unique(['politician_id', 'photo_url']);
        });

        Schema::table('candidate_news_articles', function (Blueprint $table) {
            $table->string('verification_status', 24)->default('verified')->after('provider'); // pending|verified|rejected
            $table->string('verification_reason', 255)->nullable()->after('verification_status');
            $table->decimal('verification_confidence', 4, 3)->nullable()->after('verification_reason');
            $table->decimal('name_match_score', 4, 3)->nullable()->after('verification_confidence');
            $table->decimal('context_match_score', 4, 3)->nullable()->after('name_match_score');
            $table->string('topic_key', 100)->nullable()->after('context_match_score');
            $table->decimal('topic_confidence', 4, 3)->nullable()->after('topic_key');
            $table->timestamp('verified_at')->nullable()->after('topic_confidence');
            $table->json('verification_meta')->nullable()->after('verified_at');

            $table->index(['politician_id', 'verification_status', 'published_at'], 'candidate_news_articles_verified_idx');
            $table->index(['verification_status', 'published_at'], 'candidate_news_articles_status_idx');
            $table->index(['topic_key', 'published_at'], 'candidate_news_articles_topic_idx');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_news_articles', function (Blueprint $table) {
            $table->dropIndex('candidate_news_articles_verified_idx');
            $table->dropIndex('candidate_news_articles_status_idx');
            $table->dropIndex('candidate_news_articles_topic_idx');
            $table->dropColumn([
                'verification_status',
                'verification_reason',
                'verification_confidence',
                'name_match_score',
                'context_match_score',
                'topic_key',
                'topic_confidence',
                'verified_at',
                'verification_meta',
            ]);
        });

        Schema::dropIfExists('politician_photo_quarantines');

        Schema::table('politicians', function (Blueprint $table) {
            $table->dropColumn([
                'profile_photo_status',
                'profile_photo_validation_confidence',
                'profile_photo_last_validated_at',
            ]);
        });
    }
};
