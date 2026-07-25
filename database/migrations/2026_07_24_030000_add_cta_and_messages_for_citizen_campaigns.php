<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the voter-facing engagement surface for citizen campaigns:
 *   1. Per-campaign call-to-action link shown on the watch page.
 *   2. A dedicated store for voter-reported issues and voter-to-sponsor
 *      questions on citizen campaigns.
 *
 * Citizen reports/questions are kept in their own table (citizen_campaign_messages)
 * rather than reused from voter_watch_reports, whose campaign_id FK is bound to
 * political_campaigns — mixing the two would pollute the politician report queue
 * and require risky FK surgery on SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citizen_campaigns', function (Blueprint $table): void {
            $table->string('call_to_action_url', 2048)->nullable()->after('video_blurb');
            $table->string('call_to_action_label', 60)->nullable()->after('call_to_action_url');
        });

        Schema::create('citizen_campaign_messages', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('voter_id')
                ->constrained('voters')
                ->cascadeOnDelete();

            $table->foreignId('citizen_campaign_id')
                ->constrained('citizen_campaigns')
                ->cascadeOnDelete();

            // 'issue' = voter-reported problem; 'message' = voter-to-sponsor question.
            $table->enum('type', ['issue', 'message'])->default('issue');

            // For 'issue' only: video_not_playing | incorrect_info | offensive_content | other
            $table->string('issue_category', 40)->nullable();

            $table->text('body');

            // Optional social/video reference attached to a question.
            $table->string('reference_url', 2048)->nullable();
            $table->unsignedInteger('reference_start_seconds')->nullable();
            $table->unsignedInteger('reference_end_seconds')->nullable();
            $table->string('reference_note', 280)->nullable();

            $table->enum('status', ['open', 'resolved'])->default('open');

            $table->timestamps();

            $table->index(['citizen_campaign_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citizen_campaign_messages');

        Schema::table('citizen_campaigns', function (Blueprint $table): void {
            $table->dropColumn(['call_to_action_label', 'call_to_action_url']);
        });
    }
};