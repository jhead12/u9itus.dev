<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add Video Question Support to voter_watch_reports
 * ─────────────────────────────────────────────────────────────────
 * Extends voter_watch_reports to support video questions in addition
 * to text questions. Voters can now upload video messages to politicians.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voter_watch_reports', function (Blueprint $table) {
            // Store URL to uploaded video question (MP4, WebM, etc.)
            $table->text('media_url')->nullable()->after('body');

            // Video duration in seconds
            $table->integer('media_duration')->nullable()->after('media_url');

            // Distinguish between text vs video questions
            // Values: 'text' | 'video' | 'audio' (future)
            $table->string('message_type', 20)->default('text')->after('media_duration');

            // Index for efficient filtering
            $table->index('message_type');
        });
    }

    public function down(): void
    {
        Schema::table('voter_watch_reports', function (Blueprint $table) {
            $table->dropIndex(['message_type']);
            $table->dropColumn(['media_url', 'media_duration', 'message_type']);
        });
    }
};
