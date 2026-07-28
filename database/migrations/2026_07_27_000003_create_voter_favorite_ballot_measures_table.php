<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voter_favorite_ballot_measures', function (Blueprint $table) {
            $table->id();

            $table->foreignId('voter_id')
                ->constrained('voters')
                ->cascadeOnDelete();

            $table->foreignId('ballot_measure_id')
                ->constrained('ballot_measures')
                ->cascadeOnDelete();

            $table->timestamp('favorited_at')->useCurrent();

            $table->unique(['voter_id', 'ballot_measure_id']);
            $table->index('voter_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voter_favorite_ballot_measures');
    }
};
