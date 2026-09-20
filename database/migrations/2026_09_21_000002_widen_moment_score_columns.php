<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * moment_score = log10(views) × views-per-day × decay × weights, so a clip that
 * is genuinely viral (13k views in 2 days already scores ~15,000) overflowed
 * decimal(8,4)'s 9999.9999 ceiling and failed the whole insert with MySQL 1264.
 * decimal(16,4) leaves headroom up to 10^12.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('politician_viral_moments', 'moment_score')) {
            Schema::table('politician_viral_moments', function (Blueprint $table) {
                $table->decimal('moment_score', 16, 4)->nullable()->change();
            });
        }

        if (Schema::hasColumn('politicians', 'featured_moment_score')) {
            Schema::table('politicians', function (Blueprint $table) {
                $table->decimal('featured_moment_score', 16, 4)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Intentionally not narrowed back: doing so would fail (or truncate) on
        // any row that already holds a score above 9999.9999.
    }
};
