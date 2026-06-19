<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Map interaction events — anonymous heatmap / UX analytics.
     *
     * Records every meaningful click on the 3-D map so we can see which
     * states, districts and candidates voters are most curious about.
     * No PII is stored: IPs are SHA-256 hashed before persistence.
     */
    public function up(): void
    {
        Schema::create('map_interaction_events', function (Blueprint $table) {
            $table->id();

            // Anonymous browser session (localStorage UUID — no user account needed)
            $table->string('session_id', 64)->index();

            /**
             * event_type values:
             *   region_click         – clicked a region legend row
             *   state_click          – clicked a state mesh
             *   district_click       – clicked a congressional district fill
             *   candidate_card_click – clicked a candidate card in the side panel
             *   pol_drawer_open      – politician profile drawer opened
             *   city_marker_click    – clicked a top-city census pin
             *   gov_marker_click     – clicked a state-capital pin
             *   search_opened        – opened the search palette
             *   search_result_select – selected a result from the palette
             *   layer_toggle         – toggled a map data layer chip
             */
            $table->string('event_type', 64);

            // Geographic context
            $table->string('region', 32)->nullable();   // Northeast, South, …
            $table->string('state', 64)->nullable();    // full state name
            $table->string('state_abbr', 4)->nullable(); // CA, TX, …
            $table->string('district', 16)->nullable(); // CA-38, TX-AL, …
            $table->string('party', 8)->nullable();     // D, R, I

            // Candidate context (filled for card/drawer events)
            $table->string('candidate_name', 128)->nullable();
            $table->string('candidate_slug', 255)->nullable();

            // Arbitrary extra payload (search query, layer name, city name, …)
            $table->json('meta')->nullable();

            // Privacy-safe fingerprinting — used only for bot filtering
            $table->string('ip_hash', 64)->nullable();          // SHA-256(IP)
            $table->string('user_agent_hash', 32)->nullable();  // MD5(UA)

            // Referrer helps understand organic traffic sources
            $table->string('referrer', 512)->nullable();

            $table->timestamps();

            // Analytics query patterns
            $table->index(['event_type', 'created_at']);
            $table->index(['state', 'event_type']);
            $table->index(['district', 'event_type']);
            // session_id already indexed inline on the column definition above
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_interaction_events');
    }
};
