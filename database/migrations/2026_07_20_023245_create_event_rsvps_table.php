<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Event RSVPs — yes / maybe / no / waitlist / approved / declined.
     */
    public function up(): void
    {
        Schema::create('event_rsvps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('civic_event_id')->constrained('civic_events')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('status')->default('yes'); // yes, maybe, no, waitlist, approved, declined
            $table->unsignedInteger('guest_count')->default(1);
            $table->text('notes')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['civic_event_id', 'user_id']);
            $table->index(['civic_event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_rsvps');
    }
};
