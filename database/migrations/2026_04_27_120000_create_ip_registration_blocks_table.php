<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Admin-managed allowlist/blocklist for IP addresses suspected of
     * fraudulent or abusive registration patterns. Permanent blocks can
     * have expires_at = NULL; temporary blocks have an expiry timestamp.
     */
    public function up(): void
    {
        Schema::create('ip_registration_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->unique()->index();
            $table->foreignId('blocked_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('reason', 255);
            $table->text('notes')->nullable();
            $table->timestamp('expires_at')->nullable(); // NULL = permanent block
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ip_registration_blocks');
    }
};
