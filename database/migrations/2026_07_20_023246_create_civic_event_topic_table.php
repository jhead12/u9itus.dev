<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('civic_event_topic', function (Blueprint $table) {
            $table->id();
            $table->foreignId('civic_event_id')->constrained('civic_events')->onDelete('cascade');
            $table->foreignId('politician_topic_id')->constrained('politician_topics')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['civic_event_id', 'politician_topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('civic_event_topic');
    }
};
