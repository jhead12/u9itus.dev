<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('state_election_dates', function (Blueprint $table) {
            $table->id();
            $table->string('state', 2)->index();
            $table->unsignedSmallInteger('election_year');
            $table->string('stage_name', 50); // e.g. Primary, General, Runoff, Special
            $table->date('election_date')->nullable();
            $table->date('filing_deadline')->nullable();
            $table->string('votesmart_election_id', 50)->nullable();
            $table->string('source', 50)->default('votesmart');
            $table->timestamps();

            $table->unique(['state', 'election_year', 'stage_name']);
            $table->index(['state', 'election_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_election_dates');
    }
};
