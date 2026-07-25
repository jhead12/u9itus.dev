<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ballot_measures', function (Blueprint $table) {
            $table->id();
            $table->string('state', 2)->index();
            $table->string('county', 100)->nullable();
            $table->string('measure_number', 20)->nullable();
            $table->string('title', 255);
            $table->text('summary')->nullable();
            $table->text('yes_meaning')->nullable();
            $table->text('no_meaning')->nullable();
            $table->date('election_date')->nullable();
            $table->string('status', 20)->default('upcoming'); // upcoming | passed | failed
            $table->string('source', 50)->default('manual');
            $table->string('source_url', 2048)->nullable();
            $table->timestamps();

            $table->index(['state', 'election_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ballot_measures');
    }
};
