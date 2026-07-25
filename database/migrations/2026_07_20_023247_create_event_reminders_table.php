<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks which event reminders have already been sent so we don't spam attendees.
     */
    public function up(): void
    {
        Schema::create('event_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('civic_event_id')->constrained('civic_events')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->unsignedTinyInteger('hours_before'); // e.g. 24 or 1
            $table->timestamps();

            $table->unique(['civic_event_id', 'user_id', 'hours_before']);
            $table->index(['civic_event_id', 'hours_before']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_reminders');
    }
};
