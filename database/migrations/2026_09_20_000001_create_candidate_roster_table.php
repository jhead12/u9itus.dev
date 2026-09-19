<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * People an authoritative source says exist — today, everyone with an FEC filing
 * for the cycle. Not shown anywhere: it is the "real data" that news-discovered
 * candidates are checked against before they can become public profiles (see
 * CandidateCorroboration). Kept apart from election_candidate_records so a
 * primary loser's filing can vouch for a name without ever appearing on the map.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_roster', function (Blueprint $table) {
            $table->id();
            $table->string('source', 24);                 // 'fec'
            $table->string('source_id', 64);              // e.g. FEC candidate id
            $table->string('full_name');
            $table->string('identity_key', 160)->index(); // MapCandidateHygiene::identityKey()
            $table->string('state', 2);
            $table->string('office', 12);                 // H | S | P
            $table->string('district', 16)->nullable();   // "CA-43", "AK-AL"; null for Senate
            $table->smallInteger('election_year');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'source_id', 'election_year']);
            $table->index(['state', 'identity_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_roster');
    }
};
