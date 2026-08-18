<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governor_race_candidate_counts', function (Blueprint $table) {
            $table->id();
            $table->string('state', 2)->index();
            $table->unsignedSmallInteger('election_year');
            $table->unsignedSmallInteger('expected_count');
            $table->string('source', 50)->default('ballotpedia_manual');
            $table->string('source_url')->nullable();
            $table->date('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['state', 'election_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governor_race_candidate_counts');
    }
};
