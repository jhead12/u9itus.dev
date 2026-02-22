<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 – Admin Features
 *
 * Adds:
 *  - users.suspended_at          (nullable timestamp for soft-suspension)
 *  - users.suspension_reason     (nullable string explaining suspension)
 *  - users.kyc_reviewed_at       (nullable timestamp when KYC was reviewed)
 *  - users.kyc_reviewer_id       (FK to users table – which admin reviewed)
 *  - users.kyc_rejection_reason  (nullable text)
 *  - political_campaigns.rejection_reason (nullable text for rejected campaigns)
 *  - view_sessions.reviewed_at   (nullable timestamp – admin reviewed this session)
 *  - view_sessions.reviewed_by   (nullable FK to users table)
 *  - view_sessions.review_action (nullable enum: cleared, voided, confirmed)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Users: suspension + KYC review metadata ─────────────────────────
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('is_verified');
            $table->string('suspension_reason')->nullable()->after('suspended_at');
            $table->timestamp('kyc_reviewed_at')->nullable()->after('kyc_status');
            $table->foreignId('kyc_reviewer_id')->nullable()->after('kyc_reviewed_at')
                  ->constrained('users')->nullOnDelete();
            $table->text('kyc_rejection_reason')->nullable()->after('kyc_reviewer_id');
        });

        // ── Campaigns: rejection reason ──────────────────────────────────────
        Schema::table('political_campaigns', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('approval_status');
        });

        // ── View Sessions: fraud review audit ────────────────────────────────
        Schema::table('view_sessions', function (Blueprint $table) {
            $table->timestamp('reviewed_at')->nullable()->after('fraud_score');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')
                  ->constrained('users')->nullOnDelete();
            $table->string('review_action')->nullable()->after('reviewed_by')
                  ->comment('cleared | voided | confirmed');
        });
    }

    public function down(): void
    {
        Schema::table('view_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['reviewed_at', 'review_action']);
        });

        Schema::table('political_campaigns', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kyc_reviewer_id');
            $table->dropColumn([
                'suspended_at',
                'suspension_reason',
                'kyc_reviewed_at',
                'kyc_rejection_reason',
            ]);
        });
    }
};
