<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('election_candidate_records', function (Blueprint $table) {
            $table->id();
            $table->string('source', 64); // e.g. state_feed, county_feed, ballotpedia, congress_legislators
            $table->string('external_candidate_id', 128);
            $table->string('full_name');
            $table->string('political_office')->nullable();
            $table->string('governance_level')->nullable();
            $table->string('state', 64)->nullable();
            $table->string('county', 128)->nullable();
            $table->string('city', 128)->nullable();
            $table->string('district', 128)->nullable();
            $table->string('party_affiliation', 64)->nullable();
            $table->date('election_date')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_candidate_id'], 'election_candidate_records_source_external_unique');
            $table->index(['state', 'governance_level'], 'election_candidate_records_state_level_index');
            $table->index('election_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('election_candidate_records');
    }
};
