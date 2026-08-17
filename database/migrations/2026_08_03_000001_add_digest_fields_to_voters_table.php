<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * These three columns are meaningful only for voters.user_id IS NULL rows
 * created by the lightweight guest "email me weekly updates about my saved
 * places" opt-in (map favorites digest). Real, User-linked voters use
 * notification_preferences.email_boundary_digest instead — this state gets
 * absorbed into that column and cleared once the guest registers (see
 * RegistrationController::registerVoter()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->boolean('digest_opt_in_pending')->default(false)->after('is_registered_voter');
            $table->timestamp('digest_confirmed_at')->nullable()->after('digest_opt_in_pending');
            $table->timestamp('digest_confirmation_sent_at')->nullable()->after('digest_confirmed_at');
            $table->index(['digest_opt_in_pending', 'digest_confirmed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->dropIndex(['digest_opt_in_pending', 'digest_confirmed_at']);
            $table->dropColumn(['digest_opt_in_pending', 'digest_confirmed_at', 'digest_confirmation_sent_at']);
        });
    }
};
