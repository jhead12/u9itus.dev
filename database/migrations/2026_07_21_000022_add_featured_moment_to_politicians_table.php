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
        // Per-column guards: on MySQL an ALTER ADD COLUMN auto-commits, so a
        // partial batch can leave some columns added and others not (with no
        // `migrations` row recorded). Adding each column only if missing makes
        // a re-run self-heal instead of throwing SQLSTATE 42S22/1060 duplicate
        // column. Order matters — each `after(...)` references the prior column.
        if (! Schema::hasColumn('politicians', 'featured_moment')) {
            Schema::table('politicians', function (Blueprint $table) {
                // {title, url, thumbnail_url, source, published_at, view_count}
                $table->json('featured_moment')->nullable()->after('video_links');
            });
        }

        if (! Schema::hasColumn('politicians', 'featured_moment_score')) {
            Schema::table('politicians', function (Blueprint $table) {
                $table->decimal('featured_moment_score', 8, 4)->nullable()->after('featured_moment');
            });
        }

        if (! Schema::hasColumn('politicians', 'featured_moment_published_at')) {
            Schema::table('politicians', function (Blueprint $table) {
                $table->timestamp('featured_moment_published_at')->nullable()->after('featured_moment_score');
            });
        }

        if (! Schema::hasColumn('politicians', 'featured_moment_updated_at')) {
            Schema::table('politicians', function (Blueprint $table) {
                $table->timestamp('featured_moment_updated_at')->nullable()->after('featured_moment_published_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('politicians', function (Blueprint $table) {
            // dropColumn is a no-op for columns that don't exist on MySQL when
            // passed individually, but the array form errors on a missing name —
            // only drop what's present so rollback is safe on a partial state.
            $toDrop = array_filter([
                'featured_moment',
                'featured_moment_score',
                'featured_moment_published_at',
                'featured_moment_updated_at',
            ], fn ($c) => Schema::hasColumn('politicians', $c));

            if (! empty($toDrop)) {
                $table->dropColumn(array_values($toDrop));
            }
        });
    }
};