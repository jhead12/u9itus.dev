<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('politician_cleanup_reviews', function (Blueprint $table) {
            $table->unsignedTinyInteger('severity')->nullable()->after('reason');
            $table->unsignedTinyInteger('occurrence')->nullable()->after('severity');
            $table->unsignedTinyInteger('detectability')->nullable()->after('occurrence');
            $table->unsignedSmallInteger('priority_score')->nullable()->after('detectability');
            $table->index(['status', 'priority_score']);
        });

        Schema::table('candidate_match_reviews', function (Blueprint $table) {
            $table->unsignedTinyInteger('severity')->nullable()->after('reason');
            $table->unsignedTinyInteger('occurrence')->nullable()->after('severity');
            $table->unsignedTinyInteger('detectability')->nullable()->after('occurrence');
            $table->unsignedSmallInteger('priority_score')->nullable()->after('detectability');
            $table->index(['status', 'priority_score']);
        });
    }

    public function down(): void
    {
        Schema::table('politician_cleanup_reviews', function (Blueprint $table) {
            $table->dropIndex(['status', 'priority_score']);
            $table->dropColumn(['severity', 'occurrence', 'detectability', 'priority_score']);
        });

        Schema::table('candidate_match_reviews', function (Blueprint $table) {
            $table->dropIndex(['status', 'priority_score']);
            $table->dropColumn(['severity', 'occurrence', 'detectability', 'priority_score']);
        });
    }
};
