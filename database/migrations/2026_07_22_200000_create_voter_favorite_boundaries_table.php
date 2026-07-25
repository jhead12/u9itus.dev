<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * voter_favorite_boundaries
 *
 * A voter can save any number of geographic boundaries — congressional
 * districts and top cities — so they can be pinned as quick-jump chips in the
 * 3D map's Layers → Boundaries panel. Polymorphic-by-discriminator: a row is
 * either a district (district_number set) or a city (city_name + cached
 * lat/lng set). City coordinates are cached from the TOP_CITIES dataset so the
 * map can fly to a saved city without re-resolving it client-side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voter_favorite_boundaries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('voter_id')
                ->constrained('voters')
                ->cascadeOnDelete();

            // 'district' or 'city'
            $table->string('boundary_type', 12);

            // 2-letter state abbreviation (TOP_CITIES is keyed by abbr;
            // window.__mapGoTo also accepts a 2-char abbr).
            $table->char('state_abbr', 2);

            // District side: "12", "AL" (at-large). Null for cities.
            $table->string('district_number', 4)->nullable();

            // City side: place name. Null for districts.
            $table->string('city_name', 120)->nullable();

            // Pre-rendered chip label, e.g. "California's 12th" / "Los Angeles, CA".
            $table->string('label', 160);

            // Cached city center so fly-to needs no client re-resolve.
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();

            $table->timestamp('favorited_at')->useCurrent();

            // A voter can only save a given boundary once.
            $table->unique(['voter_id', 'boundary_type', 'state_abbr', 'district_number', 'city_name'], 'voter_boundary_unique');

            $table->index('voter_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voter_favorite_boundaries');
    }
};