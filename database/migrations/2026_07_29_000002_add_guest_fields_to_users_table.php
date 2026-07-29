<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supports "Guest Trial Mode" — an admin-controlled, time-boxed setting
 * (see PlatformSettingsService key `guest_trial_mode_enabled`) that lets
 * anonymous visitors get a silently-provisioned, real-but-flagged voter
 * session (favoriting/notes work; money-related actions stay blocked —
 * see BlockGuestFromMonetization middleware).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_guest')->default(false)->index();
            // Captured once at provisioning time from the *then-current*
            // trial duration setting, so a later admin change to the
            // duration doesn't retroactively alter already-provisioned
            // guests' expiry.
            $table->timestamp('guest_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_guest', 'guest_expires_at']);
        });
    }
};
