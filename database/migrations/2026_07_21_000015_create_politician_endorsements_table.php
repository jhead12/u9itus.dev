<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * politician_endorsements
 *
 * One row per (politician, endorser group) — e.g. "Governor" or "AFL-CIO" —
 * detected by App\Services\EndorsementClassifier from already-verified
 * candidate_news_articles text (see config/endorsements.php for the
 * title/org keywords + verb phrases it looks for). Deduped per group so
 * repeat coverage of the same endorsement strengthens match_count/confidence
 * instead of creating duplicate rows; detected_article_ids keeps the full
 * evidence trail for auditability. `status` mirrors the verification_status
 * pattern used elsewhere (Organization, CandidateNewsArticle) so a false
 * positive can be hidden without deleting the evidence or a code deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('politician_endorsements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('politician_id')->constrained()->cascadeOnDelete();

            $table->string('group_key', 64);
            $table->string('label', 128); // denormalized display copy of config('endorsements.groups.*.label')

            $table->string('endorser_name', 255)->nullable(); // best-effort capture, not required for the badge
            $table->string('matched_phrase', 255);
            $table->decimal('confidence', 3, 2);

            $table->foreignId('source_article_id')
                ->nullable()
                ->constrained('candidate_news_articles')
                ->nullOnDelete();
            $table->string('source_url', 2048)->nullable();

            $table->json('detected_article_ids')->nullable(); // every article that matched this group
            $table->unsignedInteger('match_count')->default(1);

            $table->enum('status', ['detected', 'dismissed'])->default('detected');

            $table->timestamps();

            $table->unique(['politician_id', 'group_key'], 'politician_endorsements_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('politician_endorsements');
    }
};
