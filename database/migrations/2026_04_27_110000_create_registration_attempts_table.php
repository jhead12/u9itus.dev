<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Log every registration attempt (allowed or blocked) for security auditing
     * and fraud detection. Used to enforce rate limits and detect patterns.
     */
    public function up(): void
    {
        Schema::create('registration_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->index();
            $table->string('email', 255)->nullable()->index();
            $table->string('user_agent', 500)->nullable();
            $table->enum('outcome', ['allowed', 'blocked', 'flagged'])->default('allowed');
            $table->string('reason', 255)->nullable(); // e.g., "ip_blocked", "rate_limit_exceeded", "kyc_duplicate_detected"
            $table->json('metadata')->nullable(); // Extra context: count of existing accounts, etc.
            $table->timestamps();

            // Composite index for lookups by IP and date
            $table->index(['ip_address', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_attempts');
    }
};
