<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Civic scope (Local/District/State/National — see NeighborhoodGroup::SCOPES)
 * a group organizes at. Nullable and backward-compatible: groups without a
 * scope keep rendering at their existing bare /groups/{slug} URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('neighborhood_groups', function (Blueprint $table) {
            $table->string('scope')->nullable()->after('zip');
            $table->index('scope');
        });
    }

    public function down(): void
    {
        Schema::table('neighborhood_groups', function (Blueprint $table) {
            $table->dropIndex(['scope']);
            $table->dropColumn('scope');
        });
    }
};
