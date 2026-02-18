<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_view_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique()->index();
            $table->foreignId('political_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voter_id')->constrained()->cascadeOnDelete();
            $table->string('notification_method')->default('email'); // email | sms | both
            $table->string('sent_to');                               // email or phone
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->foreignId('view_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address_used')->nullable();
            $table->string('device_fingerprint_used')->nullable();
            $table->boolean('is_used')->default(false);
            $table->boolean('is_expired')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_view_tokens');
    }
};
