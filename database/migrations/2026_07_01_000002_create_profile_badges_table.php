<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * profile_badges
 *
 * Polymorphic badge assignments. One row = one badge on one profile.
 * Supports Politician, Voter, and (future) Citizen via morph columns.
 *
 * Badge types:
 *   self_declared  — user explicitly added it to their profile
 *   earned_views   — system granted after watching N topic-tagged campaigns
 *   earned_referral— system granted after referring N voters
 *   token_holder   — system granted when voter holds a politician's meToken (Web3, Phase 20+)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_badges', function (Blueprint $table) {
            $table->id();

            // Polymorphic owner: Politician, Voter, (future) Citizen
            $table->morphs('badgeable');

            $table->foreignId('topic_id')
                ->constrained('politician_topics')
                ->cascadeOnDelete();

            $table->enum('badge_type', [
                'self_declared',
                'earned_views',
                'earned_referral',
                'token_holder',
            ])->default('self_declared');

            // For earned badges: the view/referral count that triggered the award
            $table->unsignedInteger('earned_threshold')->nullable();

            // Whether the badge appears publicly on the profile
            $table->boolean('is_public')->default(true);

            $table->timestamp('earned_at')->nullable();
            $table->timestamps();

            // One badge per topic per profile — no duplicates
            $table->unique(['badgeable_type', 'badgeable_id', 'topic_id'], 'profile_badges_unique');
            $table->index(['badgeable_type', 'badgeable_id'], 'profile_badges_owner_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_badges');
    }
};
