<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * voter_favorite_politicians
 *
 * Simple join table: a voter can bookmark any number of politicians.
 * Used to power:
 *   - "Politicians I Follow" card on the voter dashboard
 *   - "X supporters" count on the politician's public profile
 *   - (Phase 20+) XMTP broadcast list — politician messages their favorited voters
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voter_favorite_politicians', function (Blueprint $table) {
            $table->id();

            $table->foreignId('voter_id')
                ->constrained('voters')
                ->cascadeOnDelete();

            $table->foreignId('politician_id')
                ->constrained('politicians')
                ->cascadeOnDelete();

            $table->timestamp('favorited_at')->useCurrent();

            // A voter can only favorite a politician once
            $table->unique(['voter_id', 'politician_id']);

            // Fast lookups in both directions
            $table->index('voter_id');
            $table->index('politician_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voter_favorite_politicians');
    }
};
