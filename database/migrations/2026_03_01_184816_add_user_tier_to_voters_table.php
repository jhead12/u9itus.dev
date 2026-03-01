<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add user tier tracking for early adopter benefits and tier-based pricing.
     */
    public function up(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->string('user_tier')->nullable()->after('referral_code'); // 'early_adopter', 'regular', null
            $table->timestamp('early_adopter_until')->nullable()->after('user_tier'); // Optional expiry for early adopter status
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->dropColumn(['user_tier', 'early_adopter_until']);
        });
    }
};
