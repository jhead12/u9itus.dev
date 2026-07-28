<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voter_politician_notes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('voter_id')
                ->constrained('voters')
                ->cascadeOnDelete();

            $table->foreignId('politician_id')
                ->constrained('politicians')
                ->cascadeOnDelete();

            $table->text('body');

            $table->timestamps();

            // One running note per voter+politician pair.
            $table->unique(['voter_id', 'politician_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voter_politician_notes');
    }
};
