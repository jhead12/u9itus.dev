<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What members of Congress said in the Congressional Record (GovInfo CREC), one row per
 * member per Record article, keyed by Bioguide ID like the roll-call and committee tables.
 * A debate article with five speakers yields five rows, each holding only that member's
 * words, so topic and position analysis is about the speaker and not their opponents.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('congress_floor_speeches')) {
            Schema::create('congress_floor_speeches', function (Blueprint $table) {
                $table->id();
                // GovInfo granule, e.g. CREC-2025-07-15-pt1-PgH3249-4.
                $table->string('granule_id', 80);
                $table->string('bioguide_id', 12);
                // house | senate
                $table->string('chamber', 10);
                // floor: spoken in session (has video) | written: Extensions of Remarks.
                $table->string('kind', 10)->default('floor');
                $table->date('spoken_on');
                // Time the Record assigns to the article; coarse, kept for future video matching.
                $table->time('record_time')->nullable();
                $table->string('title', 500);
                $table->string('record_section', 40)->nullable();
                $table->string('citation', 60)->nullable();
                $table->longText('body');
                $table->unsignedInteger('word_count')->default(0);
                $table->string('source_url', 500);

                // Analysis (congress:analyze-floor-speeches).
                $table->string('topic_key', 80)->nullable();
                $table->decimal('topic_confidence', 4, 3)->nullable();
                // support | oppose | mixed; null when the speech takes no position.
                $table->string('stance', 10)->nullable();
                $table->string('position_summary', 500)->nullable();
                // Verbatim excerpt; only kept when it is found in body.
                $table->text('quote')->nullable();
                // keyword | llm
                $table->string('analysis_method', 10)->nullable();
                $table->timestamp('analyzed_at')->nullable();
                $table->timestamps();

                $table->unique(['granule_id', 'bioguide_id'], 'congress_floor_speeches_unique');
                $table->index(['bioguide_id', 'spoken_on'], 'congress_floor_speeches_member_idx');
                $table->index('analyzed_at');
            });
        }

        if (! Schema::hasColumn('politician_topic_signals', 'floor_speech_count')) {
            Schema::table('politician_topic_signals', function (Blueprint $table) {
                $table->unsignedInteger('floor_speech_count')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('politician_topic_signals', 'floor_speech_count')) {
            Schema::table('politician_topic_signals', function (Blueprint $table) {
                $table->dropColumn('floor_speech_count');
            });
        }

        Schema::dropIfExists('congress_floor_speeches');
    }
};
