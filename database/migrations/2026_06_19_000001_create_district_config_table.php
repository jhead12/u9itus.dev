<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the active congressional boundary configuration so the map can
 * dynamically adapt to new Congress sessions without a code deploy.
 *
 * Synced by: php artisan geo:sync-district-config
 * Consumed by: GET /api/v1/map/district-config
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('district_config', function (Blueprint $table) {
            $table->id();

            // e.g. 119, 120, 121 — the ordinal Congress number
            $table->smallInteger('congress_number')->default(119);

            // TIGERweb Legislative MapServer layer index.
            // Layer 0 is always the current/latest Congress on Census TIGERweb.
            // Update when Census publishes a dedicated historical layer.
            $table->smallInteger('tigerweb_layer')->default(0);

            // The GeoJSON feature-property field returned by TIGERweb for this Congress.
            // e.g. 'CD119' for the 119th, 'CD120' for the 120th.
            $table->string('cd_field', 8)->default('CD119');

            // Human-readable label shown on the map panel.
            // e.g. '119th Congress (2025–2027)'
            $table->string('congress_label', 80)->nullable();

            // JSON object keyed by district label → party code.
            // e.g. {"CA-1": "D", "CA-2": "R", "AK-AL": "R"}
            // Built from the politicians table (seated federal House members).
            $table->text('party_map')->nullable();

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('district_config');
    }
};
