<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 14 — Repeat Viewing
 *
 * Adds three columns to political_campaigns that let a politician opt in to
 * allowing the same voter to watch their ad more than once, with configurable
 * rate-limiting so the fraud-prevention system stays effective.
 *
 *   allow_repeat_views         — master toggle (default off)
 *   repeat_view_cooldown_hours — minimum gap between re-watches (default 24 h)
 *   max_views_per_voter        — hard cap on lifetime views per voter (default 1)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('political_campaigns', function (Blueprint $table): void {
            $table->boolean('allow_repeat_views')
                  ->default(false)
                  ->after('completed_at')
                  ->comment('Politician opt-in: allow a voter to watch this ad more than once');

            $table->unsignedSmallInteger('repeat_view_cooldown_hours')
                  ->default(24)
                  ->after('allow_repeat_views')
                  ->comment('Minimum hours between repeat views by the same voter');

            $table->unsignedTinyInteger('max_views_per_voter')
                  ->default(1)
                  ->after('repeat_view_cooldown_hours')
                  ->comment('Hard lifetime limit on how many times one voter may watch this campaign');
        });
    }

    public function down(): void
    {
        Schema::table('political_campaigns', function (Blueprint $table): void {
            $table->dropColumn([
                'allow_repeat_views',
                'repeat_view_cooldown_hours',
                'max_views_per_voter',
            ]);
        });
    }
};
