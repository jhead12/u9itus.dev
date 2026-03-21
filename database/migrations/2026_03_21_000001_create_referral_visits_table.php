<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_visits', function (Blueprint $table) {
            $table->id();
            $table->string('referral_code', 20)->index();
            $table->foreignId('referrer_voter_id')->nullable()->constrained('voters')->nullOnDelete();
            $table->foreignId('referrer_politician_id')->nullable()->constrained('politicians')->nullOnDelete();
            $table->string('session_id', 255)->nullable()->index();
            $table->string('landing_path', 2048)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('converted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('converted_user_type', 40)->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index(['referrer_voter_id', 'converted_at']);
            $table->index(['referrer_politician_id', 'converted_at']);
            $table->index(['referral_code', 'session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_visits');
    }
};
