<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends politician_topics to serve as the badge catalog.
 * Adding display and eligibility fields so topics can be shown
 * as visual badges on politician, voter, and citizen profiles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('politician_topics', function (Blueprint $table) {
            // Optional SVG/PNG badge icon URL (falls back to `icon` emoji column)
            $table->string('badge_icon_url')->nullable()->after('icon');
            // Hex color for the badge chip, e.g. '#6366f1'
            $table->string('badge_color', 7)->default('#6366f1')->after('badge_icon_url');
            // Whether voters/citizens can self-declare this badge
            $table->boolean('voter_selectable')->default(true)->after('badge_color');
            // When true, only the system can grant this badge (not user-chosen)
            $table->boolean('auto_earned_only')->default(false)->after('voter_selectable');
        });
    }

    public function down(): void
    {
        Schema::table('politician_topics', function (Blueprint $table) {
            $table->dropColumn(['badge_icon_url', 'badge_color', 'voter_selectable', 'auto_earned_only']);
        });
    }
};
