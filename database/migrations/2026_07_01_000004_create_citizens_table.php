<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citizens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('full_name');
            $table->string('business_name')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('zip')->nullable();
            $table->text('bio')->nullable();
            $table->string('profile_photo_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('referral_code', 16)->unique();
            $table->string('slug')->unique();
            $table->foreignId('referred_by_voter_id')->nullable()->constrained('voters')->nullOnDelete();
            $table->foreignId('referred_by_politician_id')->nullable()->constrained('politicians')->nullOnDelete();
            // Forward-compatible placeholder for Sprint 8.5 Neighborhood Groups.
            $table->unsignedBigInteger('neighborhood_group_id')->nullable();
            // Stripe Identity verification (lightweight — no Connect account required).
            $table->string('stripe_verification_session_id')->nullable();
            $table->timestamp('stripe_verified_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['state', 'city']);
            $table->index('zip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citizens');
    }
};
