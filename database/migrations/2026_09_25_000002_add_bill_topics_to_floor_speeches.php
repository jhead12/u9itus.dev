<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a floor speech take its topic from the bill it is about.
 *
 *   - congress_floor_speeches.bill_refs: the bills GovInfo tags as the subject of the
 *     Record article, from its most central place (title, else headline, else first
 *     paragraph; passing mentions left out), e.g. ["119-hr-3633"].
 *   - congress_floor_speeches.topic_source: where topic_key came from — vote (a tagged
 *     roll call on the bill), bill (its title or policy area), llm or keyword (the text).
 *   - congress_bills: each referenced bill's title and Congress.gov policy area, fetched
 *     once, with the topic they map to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('congress_floor_speeches', function (Blueprint $table) {
            if (! Schema::hasColumn('congress_floor_speeches', 'bill_refs')) {
                $table->json('bill_refs')->nullable();
                $table->string('topic_source', 10)->nullable();
            }
        });

        if (! Schema::hasTable('congress_bills')) {
            Schema::create('congress_bills', function (Blueprint $table) {
                $table->id();
                // "119-hr-3633": congress, lowercase type, number.
                $table->string('bill_key', 30)->unique();
                $table->string('title', 500)->nullable();
                $table->string('policy_area', 120)->nullable();
                // politician_topics slug from the title keywords or the policy area; null when neither maps.
                $table->string('topic_key', 80)->nullable();
                $table->timestamp('fetched_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('congress_bills');

        Schema::table('congress_floor_speeches', function (Blueprint $table) {
            $table->dropColumn(['bill_refs', 'topic_source']);
        });
    }
};
