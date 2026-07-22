<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * featured_moment_* on politicians
 *
 * Denormalized copy of the single highest-scoring eligible viral moment per
 * politician, written by the enricher whenever it re-promotes a moment to
 * is_featured. The map view renders many pins at once, so reading the featured
 * clip from one column here (instead of joining politician_viral_moments +
 * ranking per pin) keeps the map query cheap. The profile page still reads the
 * full ranked list from politician_viral_moments; this column is the map-pin
 * shortcut, with the score exposed for sort/badge use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('politicians', function (Blueprint $table) {
            // {title, url, thumbnail_url, source, published_at, view_count}
            $table->json('featured_moment')->nullable()->after('video_links');
            $table->decimal('featured_moment_score', 8, 4)->nullable()->after('featured_moment');
            $table->timestamp('featured_moment_published_at')->nullable()->after('featured_moment_score');
            $table->timestamp('featured_moment_updated_at')->nullable()->after('featured_moment_published_at');
        });
    }

    public function down(): void
    {
        Schema::table('politicians', function (Blueprint $table) {
            $table->dropColumn([
                'featured_moment',
                'featured_moment_score',
                'featured_moment_published_at',
                'featured_moment_updated_at',
            ]);
        });
    }
};