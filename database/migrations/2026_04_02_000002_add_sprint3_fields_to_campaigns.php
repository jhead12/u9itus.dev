<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('political_campaigns', function (Blueprint $table) {
            // Media type discriminator: youtube, vimeo, direct_file, s3_cloudfront, hls_stream
            $table->enum('media_type', ['youtube', 'vimeo', 'direct_file', 's3_cloudfront', 'hls_stream'])
                ->default('youtube')
                ->after('media_url')
                ->comment('Video source type for player selection');

            // Q&A campaign intro text
            $table->text('intro_text')->nullable()
                ->after('message_summary')
                ->comment('Intro/key message for Q&A campaigns');

            // Q&A payload: array of {question, answer} objects
            $table->json('qa_items')->nullable()
                ->after('intro_text')
                ->comment('Q&A pairs: [{"question":"...", "answer":"..."}]');

            // Post-view engagement survey: {question, options: [...], optional_cta}
            $table->json('engagement_survey')->nullable()
                ->after('qa_items')
                ->comment('Survey metadata: {question, options: [{text, value}], cta_text, cta_url}');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('political_campaigns', function (Blueprint $table) {
            $table->dropColumn(['media_type', 'intro_text', 'qa_items', 'engagement_survey']);
        });
    }
};
