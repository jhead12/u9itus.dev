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
        Schema::create('politician_topics', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();  // e.g. 'Healthcare', 'Climate Action'
            $table->string('slug')->unique();  // e.g. 'healthcare', 'climate-action'
            $table->text('description')->nullable();
            $table->string('icon', 64)->nullable();  // emoji or icon name
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Create pivot table for campaign-topic relations
        Schema::create('campaign_topic', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')
                ->constrained('political_campaigns')
                ->cascadeOnDelete();
            $table->foreignId('topic_id')
                ->constrained('politician_topics')
                ->cascadeOnDelete();
            $table->unique(['campaign_id', 'topic_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_topic');
        Schema::dropIfExists('politician_topics');
    }
};
