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
        Schema::create('loyalty_viewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('gender')->nullable();
            $table->string('age_range')->nullable();
            $table->json('preferred_cities')->nullable();
            $table->json('preferred_states')->nullable();
            $table->string('paypal_email')->nullable();
            $table->string('cashapp_tag')->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('total_earned', 10, 2)->default(0);
            $table->decimal('pending_earnings', 10, 2)->default(0);
            $table->integer('total_views')->default(0);
            $table->decimal('trust_score', 5, 2)->default(100);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_viewers');
    }
};
