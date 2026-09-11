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
        Schema::create('politician_cleanup_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('review_type', 32); // merge|deactivate|name_reject
            $table->foreignId('politician_id')->constrained()->cascadeOnDelete();
            $table->foreignId('duplicate_politician_id')->nullable()->constrained('politicians')->cascadeOnDelete();
            $table->json('payload')->nullable();
            $table->string('status', 32)->default('pending'); // pending|approved|rejected
            $table->text('reason')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // NULLs aren't equal to each other in a unique index (MySQL,
            // Postgres, and SQLite all treat every NULL as distinct), so a
            // DB-level unique constraint can't stop re-flagging a
            // duplicate_politician_id-less row (deactivate/name_reject) —
            // idempotency for those is enforced in
            // PoliticianCleanupReview::enqueue() instead (checks for an
            // existing pending row with the same key before inserting).
            $table->index('status');
            $table->index('review_type');
            $table->index(['review_type', 'politician_id', 'duplicate_politician_id', 'status'], 'politician_cleanup_reviews_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('politician_cleanup_reviews');
    }
};
