<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 — fraud_signals
 *
 * Immutable per-event log of every individual fraud signal raised against
 * a voter.  Unlike the coarse `flagged_for_fraud` boolean on `voters`, this
 * table captures granular signal history:
 *  • what triggered the signal (signal_type)
 *  • the score impact at point-of-evaluation
 *  • device / IP context
 *  • optional resolution (admin clears a false positive)
 *
 * This enables:
 *  ─ Admin fraud dashboard drill-down
 *  ─ ML feature engineering (future)
 *  ─ Audit trail for disputed payouts
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fraud_signals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('voter_id')
                ->constrained('voters')
                ->cascadeOnDelete();

            // Soft-reference to the view session that triggered the signal.
            $table->uuid('view_session_uuid')->nullable()->index();

            /*
             * Signal categories (mirrors FraudPreventionService flag names):
             *
             *  daily_limit_exceeded       rate_limit
             *  missing_device_fingerprint device_fingerprint
             *  device_fingerprint_mismatch device_fingerprint
             *  ip_shared_by_many_voters   ip_anomaly
             *  rapid_fire_views           behavioral
             *  previously_flagged         meta
             *  vpn_detected               ip_reputation
             *  datacenter_ip              ip_reputation
             *  tor_exit_node              ip_reputation
             *  bot_user_agent             device_fingerprint
             */
            $table->string('signal_type', 60)->index();

            // Human-readable description / reason.
            $table->string('description', 255)->nullable();

            // Numeric score contribution at the time the signal was raised.
            $table->unsignedSmallInteger('score_impact')->default(0);

            // Context snapshot
            $table->string('ip_address', 45)->nullable();
            $table->string('device_fingerprint', 64)->nullable();
            $table->string('provider', 100)->nullable();   // VPN/datacenter provider name
            $table->json('metadata')->nullable();          // Any extra structured data

            // Resolution (admin can clear false positives)
            $table->boolean('is_resolved')->default(false)->index();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('resolution_note', 500)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_signals');
    }
};
