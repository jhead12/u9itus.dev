<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add 'inferred_discourse' to profile_badges.badge_type — the system-granted
 * badge type for issue labels inferred from a politician's public discourse
 * (news + viral moments + Vote Smart positions). Granted by
 * BadgeService::grantInferredBadges() when a politician_topic_signal crosses
 * the configured threshold. The unique (badgeable_type, badgeable_id, topic_id)
 * index is unchanged, so an inferred grant uses firstOrCreate and will NOT
 * overwrite a topic a politician already self-declared.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_badges', function (Blueprint $table) {
            $table->enum('badge_type', [
                'self_declared',
                'earned_views',
                'earned_referral',
                'token_holder',
                'inferred_discourse',
            ])->default('self_declared')->change();
        });
    }

    public function down(): void
    {
        Schema::table('profile_badges', function (Blueprint $table) {
            $table->enum('badge_type', [
                'self_declared',
                'earned_views',
                'earned_referral',
                'token_holder',
            ])->default('self_declared')->change();
        });
    }
};