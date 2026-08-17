<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('causes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('topic_id')
                ->constrained('politician_topics')
                ->cascadeOnDelete();

            $table->string('title', 255);
            $table->text('description')->nullable();

            // 2-letter state abbreviation. Null means national in scope.
            $table->char('state', 2)->nullable();
            $table->string('county', 100)->nullable();

            $table->string('status', 20)->default('active');
            $table->string('source_url', 2048)->nullable();

            $table->timestamps();

            $table->index('topic_id');
            $table->index(['state', 'county']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('causes');
    }
};
