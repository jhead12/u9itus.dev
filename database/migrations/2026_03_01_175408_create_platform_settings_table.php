<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Dynamic platform settings for pricing, commissions, and thresholds.
     * Allows admin to adjust values without code changes — supports promotions,
     * early adopter bonuses, and A/B testing.
     */
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->index();  // e.g., 'revenue_per_view', 'voter_payout_promo'
            $table->string('value');                   // Stored as string, cast by service
            $table->string('type')->default('float');  // float, int, boolean, string
            $table->string('description')->nullable(); // Human-readable description for admin UI
            $table->string('category')->default('pricing'); // pricing, fraud, video, referral
            $table->timestamp('effective_from')->nullable(); // For time-bound promotions
            $table->timestamp('effective_until')->nullable(); // Auto-expires after this date
            $table->string('user_tier')->nullable(); // null = all, 'early_adopter', 'regular'
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // Extra context (promo name, A/B test group, etc.)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
