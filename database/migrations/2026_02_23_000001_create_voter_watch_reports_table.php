<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * voter_watch_reports
 * ─────────────────────────────────────────────────────────────────
 * Stores two kinds of in-watch-page voter interactions:
 *
 *  • type = 'issue'   — technical / content error report  → routes to admin
 *  • type = 'message' — direct message to the politician  → routes to politician
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voter_watch_reports', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('voter_id')
                ->constrained('voters')
                ->cascadeOnDelete();

            $table->foreignId('campaign_id')
                ->constrained('political_campaigns')
                ->cascadeOnDelete();

            $table->string('view_session_uuid', 36)->nullable(); // soft reference

            // Interaction type
            $table->enum('type', ['issue', 'message'])->default('issue')->index();

            // For 'issue': one of video_not_playing | incorrect_info | offensive_content | other
            // For 'message': null
            $table->string('issue_category', 40)->nullable();

            $table->text('body');  // voter-supplied free-text

            // Lifecycle (admin / politician can mark as resolved)
            $table->enum('status', ['open', 'in_review', 'resolved', 'dismissed'])
                ->default('open')
                ->index();

            $table->text('admin_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voter_watch_reports');
    }
};
