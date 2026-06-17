<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('politicians', function (Blueprint $table) {
            // Email the claimant entered when requesting to own this profile
            $table->string('claim_email')->nullable()->after('verification_email');
            // Signed token emailed to the claimant; null once consumed
            $table->string('claim_token', 128)->nullable()->unique()->after('claim_email');
            // When the token was issued (for expiry checks)
            $table->timestamp('claim_requested_at')->nullable()->after('claim_token');
        });
    }

    public function down(): void
    {
        Schema::table('politicians', function (Blueprint $table) {
            $table->dropColumn(['claim_email', 'claim_token', 'claim_requested_at']);
        });
    }
};
