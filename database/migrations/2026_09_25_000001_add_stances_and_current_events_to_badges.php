<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Positions on issue badges.
 *
 *   - politician_topics.kind marks a topic as an ongoing issue or a current event, and
 *     support_label / oppose_label define what "for" and "against" mean for it. Only
 *     topics with both labels ever show a position, since "Opposes Education" is not a
 *     position anyone holds.
 *   - congress_vote_topics: an editor ties a roll call to a topic and says what a yea
 *     vote means, because on a war powers resolution yea is a vote to limit the action.
 *   - profile_badges.stance / stance_label carry the position shown on the badge, and
 *     badge_type 'roll_call_vote' marks a badge earned from those tagged votes.
 *   - congress_floor_speeches.topic_stance is the speech's position on the topic's own
 *     labels, separate from `stance` (for or against the specific bill discussed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('politician_topics', function (Blueprint $table) {
            if (! Schema::hasColumn('politician_topics', 'kind')) {
                $table->string('kind', 20)->default('issue');
                $table->string('support_label', 120)->nullable();
                $table->string('oppose_label', 120)->nullable();
            }
        });

        Schema::table('profile_badges', function (Blueprint $table) {
            $table->enum('badge_type', [
                'self_declared',
                'earned_views',
                'earned_referral',
                'token_holder',
                'inferred_discourse',
                'roll_call_vote',
            ])->default('self_declared')->change();
        });

        Schema::table('profile_badges', function (Blueprint $table) {
            if (! Schema::hasColumn('profile_badges', 'stance')) {
                // support | oppose | mixed
                $table->string('stance', 10)->nullable();
                $table->string('stance_label', 255)->nullable();
            }
        });

        Schema::table('congress_floor_speeches', function (Blueprint $table) {
            if (! Schema::hasColumn('congress_floor_speeches', 'topic_stance')) {
                $table->string('topic_stance', 10)->nullable();
            }
        });

        Schema::table('politician_topic_signals', function (Blueprint $table) {
            if (! Schema::hasColumn('politician_topic_signals', 'support_count')) {
                $table->unsignedSmallInteger('support_count')->default(0);
                $table->unsignedSmallInteger('oppose_count')->default(0);
            }
        });

        if (! Schema::hasTable('congress_vote_topics')) {
            Schema::create('congress_vote_topics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('congress_vote_id')->constrained('congress_votes')->cascadeOnDelete();
                $table->foreignId('topic_id')->constrained('politician_topics')->cascadeOnDelete();
                // Which of the topic's labels a yea vote matches: support | oppose.
                $table->string('yea_stance', 10);
                // What the badge says, e.g. "Voted to limit military action against Iran".
                $table->string('yea_label', 255);
                $table->string('nay_label', 255);
                $table->string('note', 500)->nullable();
                $table->foreignId('tagged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['congress_vote_id', 'topic_id'], 'congress_vote_topics_unique');
            });
        }

        $this->seedTopics();
    }

    public function down(): void
    {
        Schema::dropIfExists('congress_vote_topics');

        DB::table('profile_badges')->where('badge_type', 'roll_call_vote')->delete();

        Schema::table('profile_badges', function (Blueprint $table) {
            $table->dropColumn(['stance', 'stance_label']);
        });
        Schema::table('profile_badges', function (Blueprint $table) {
            $table->enum('badge_type', [
                'self_declared',
                'earned_views',
                'earned_referral',
                'token_holder',
                'inferred_discourse',
            ])->default('self_declared')->change();
        });

        Schema::table('politician_topic_signals', function (Blueprint $table) {
            $table->dropColumn(['support_count', 'oppose_count']);
        });
        Schema::table('congress_floor_speeches', function (Blueprint $table) {
            $table->dropColumn('topic_stance');
        });
        Schema::table('politician_topics', function (Blueprint $table) {
            $table->dropColumn(['kind', 'support_label', 'oppose_label']);
        });
    }

    /**
     * Data Centers becomes its own topic (its keywords move out of Technology & AI
     * Policy), and the Iran conflict becomes a current-event topic that takes the
     * "iran" keyword from Foreign Policy so coverage of it is not split.
     */
    private function seedTopics(): void
    {
        $now = now();
        $nextSort = (int) DB::table('politician_topics')->max('sort_order') + 1;

        $topics = [
            'data-centers' => [
                'name' => 'Data Centers',
                'icon' => '🖥️',
                'kind' => 'issue',
                'description' => 'Building and siting data centers, including their power and water use and public incentives for them.',
                'keywords' => ['data center', 'data centers', 'hyperscale', 'hyperscaler', 'server farm'],
                'support_label' => 'Supports data center development',
                'oppose_label' => 'Opposes data center development',
                'voter_selectable' => true,
                'auto_earned_only' => false,
            ],
            'iran-conflict' => [
                'name' => 'Iran Conflict',
                'icon' => '🕊️',
                'kind' => 'current_event',
                'description' => 'U.S. military action involving Iran, including war powers resolutions in Congress.',
                'keywords' => ['iran', 'iranian', 'tehran', 'irgc', 'war powers'],
                'support_label' => 'Supports U.S. military action against Iran',
                'oppose_label' => 'Opposes U.S. military action against Iran',
                'voter_selectable' => false,
                'auto_earned_only' => true,
            ],
        ];

        foreach ($topics as $slug => $topic) {
            if (DB::table('politician_topics')->where('slug', $slug)->exists()) {
                continue;
            }
            DB::table('politician_topics')->insert(array_merge($topic, [
                'slug' => $slug,
                'keywords' => json_encode($topic['keywords']),
                'sort_order' => $nextSort++,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $this->removeKeywords('technology-ai-policy', ['data center', 'data centers']);
        $this->removeKeywords('foreign-policy', ['iran']);

        Cache::forget('issues:topic-catalog-v2');
        Cache::forget('issues:topic-catalog-v3');
        Cache::forget('issues:slug-to-id');
    }

    private function removeKeywords(string $slug, array $remove): void
    {
        $row = DB::table('politician_topics')->where('slug', $slug)->first(['id', 'keywords']);
        if (! $row || ! $row->keywords) {
            return;
        }
        $keywords = array_values(array_diff((array) json_decode($row->keywords, true), $remove));
        DB::table('politician_topics')->where('id', $row->id)->update(['keywords' => json_encode($keywords)]);
    }
};
