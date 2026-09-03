<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registry of the *official* election authority + its published URLs for every
 * US jurisdiction that can put something on a ballot: 50 states + DC, ~3,100
 * counties, and the subset of cities/townships that run their own measures.
 *
 * This is the lookup table the scrapers read from — "for OCD division X, the
 * ballot-measure page is at URL Y, served by vendor Z, last verified on D".
 * It does not hold measure content itself; that stays in `ballot_measures`.
 *
 * Primary key is the OCD division ID
 * (ocd-division/country:us/state:ca/county:los_angeles) so rows join cleanly to
 * Google Civic, the Voting Information Project feeds, and Ballotpedia.
 *
 * See doc/CIVIC_SOURCE_REGISTRY.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_data_sources', function (Blueprint $table) {
            $table->id();

            // ── Identity ────────────────────────────────────────────────────
            $table->string('ocd_id', 255)->unique(); // canonical key
            $table->string('level', 24);             // state|county|municipal|township|special
            $table->char('state', 2);               // USPS, uppercase
            $table->string('jurisdiction_name', 255);
            $table->char('county_fips', 5)->nullable();  // state(2) + county(3)
            $table->char('place_fips', 7)->nullable();   // state(2) + place(5)

            // ── Authority + platform ────────────────────────────────────────
            $table->string('authority_name', 255)->nullable(); // "Los Angeles County Registrar-Recorder/County Clerk"
            $table->string('vendor', 64)->nullable();          // voteinfo.net|granicus|ballottrax|democracy_live|homegrown|...
            $table->string('platform_template', 64)->nullable(); // scraper-adapter key; one adapter serves a whole vendor family

            // ── URLs (named columns for the ones every scraper wants; `urls`
            //    json for anything else the source hands us) ─────────────────
            $table->string('elections_home_url', 2048)->nullable();
            $table->string('sample_ballot_url', 2048)->nullable();
            $table->string('ballot_measures_url', 2048)->nullable();
            $table->string('results_url', 2048)->nullable();
            $table->string('vip_feed_url', 2048)->nullable();
            $table->string('ballotpedia_url', 2048)->nullable();
            $table->json('urls')->nullable();

            // ── Provenance + health ─────────────────────────────────────────
            $table->string('source_of_record', 24)->default('manual'); // eac|nass|census|google_civic|vip|ballotpedia|manual
            $table->boolean('robots_ok')->nullable();                  // null = not yet checked
            $table->string('scrape_status', 24)->default('unverified'); // unverified|ok|blocked|dead|redirected
            $table->text('notes')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('last_scraped_at')->nullable();

            $table->timestamps();

            $table->index('level');
            $table->index('state');
            $table->index('vendor');
            $table->index('county_fips');
            $table->index(['level', 'state']);
            $table->index(['scrape_status', 'last_verified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_data_sources');
    }
};
