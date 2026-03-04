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
        Schema::create('user_onboarding_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('user_type', ['voter', 'politician', 'admin'])->index();
            $table->string('current_phase', 50)->default('welcome');
            $table->json('completed_phases')->nullable(); // Array of completed phase keys
            $table->json('phase_data')->nullable(); // Store additional data per phase
            $table->boolean('is_completed')->default(false)->index();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('skipped')->default(false); // Allow users to skip onboarding
            $table->timestamps();

            // Ensure one record per user
            $table->unique(['user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_onboarding_progress');
    }
};
