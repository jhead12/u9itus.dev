<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * campaign_dispatches — one row per (campaign, channel, recipient) attempt.
 *
 * This is the provenance + audit table for the marketing system: every piece
 * dispatched through any channel lands here with its status, the provider's
 * message id (for webhook reconciliation of delivered/bounced), per-recipient
 * cost in cents, and a skip/fail reason. It is the raw material for:
 *   - per-channel cost reconciliation against campaign budgets,
 *   - FEC disbursement reporting for political direct mail / paid media,
 *   - the geofenced-QR "within the advertiser's territory" proof-of-delivery
 *     (future direct_mail channel writes the signed territory token here).
 *
 * Polymorphic on the campaign so political + citizen campaigns share one log.
 * voter_id is nullable: email/sms target registered voters; future direct-mail
 * targets skiptraced addresses that may not correspond to a registered voter.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('campaign_dispatches')) {
            return;
        }

        Schema::create('campaign_dispatches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->morphs('campaign');
            $table->foreignId('marketing_channel_id')
                ->constrained('marketing_channels')
                ->cascadeOnDelete();
            $table->foreignId('voter_id')
                ->nullable()
                ->constrained('voters')
                ->nullOnDelete();

            $table->string('channel_type', 32);     // denormalized for indexed filtering
            $table->string('status', 32)->default('queued');
            $table->string('provider_message_id', 255)->nullable();
            $table->json('payload')->nullable();     // snapshot of what was sent
            $table->unsignedInteger('cost_cents')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_type', 'campaign_id', 'status']);
            $table->index(['marketing_channel_id', 'status']);
            $table->index('provider_message_id');
            $table->index('voter_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_dispatches');
    }
};