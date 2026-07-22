<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Civic events — town halls, ballot-measure drives, community meetings,
     * rallies, workshops, and fundraisers hosted by citizens or politicians.
     */
    public function up(): void
    {
        Schema::create('civic_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug')->unique();

            // Polymorphic host: Citizen, Politician, or later NeighborhoodGroup.
            $table->string('host_type');
            $table->unsignedBigInteger('host_id');
            $table->index(['host_type', 'host_id']);

            $table->string('event_type'); // town_hall, ballot_measure_drive, ...
            $table->string('status')->index(); // draft, pending_approval, published, cancelled, completed

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location_name')->nullable();
            $table->string('venue_name')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('state', 4)->nullable()->index();
            $table->string('zip', 16)->nullable();

            // Geo location for map pins.
            $table->decimal('latitude', 10, 8)->nullable()->index();
            $table->decimal('longitude', 11, 8)->nullable()->index();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('timezone', 64)->default('America/New_York');

            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('rsvp_requires_approval')->default(false);
            $table->boolean('is_virtual')->default(false);
            $table->text('virtual_url')->nullable();

            $table->string('image_url')->nullable();
            $table->string('banner_url')->nullable();

            $table->unsignedInteger('goal_amount_cents')->nullable(); // fundraising / signature goal

            // Optional link to a blog post (recap / preview).
            $table->foreignId('related_post_id')->nullable()->index()->constrained('posts')->onDelete('set null');

            // Optional link to a neighborhood group (Sprint 8.5 — deferred).
            $table->unsignedBigInteger('group_id')->nullable()->index();

            $table->timestamps();

            $table->index(['status', 'starts_at']);
            $table->index(['event_type', 'status']);
            $table->index(['state', 'status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('civic_events');
    }
};
