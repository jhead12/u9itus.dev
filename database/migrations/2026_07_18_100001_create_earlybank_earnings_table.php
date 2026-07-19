<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inbound Early-bank earnings ledger.
 *
 * Records commission / bonus / payout events that Early-bank.com sends to
 * U9itus via POST /api/v1/earlybank/webhook. U9itus is NOT the source of truth
 * for these amounts — Early-bank is — but this table lets us display EB
 * earnings in voter/politician summaries and audit what EB reported.
 *
 * Kept separate from payout_attempts (u9itus -> voter payouts) and from
 * earlybank_webhook_logs (outbound audit). Each row is idempotent by
 * earlybank_event_id + idempotency_key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('earlybank_earnings', function (Blueprint $table) {
            $table->id();

            // Early-bank's own event id, if provided. Unique prevents duplicates.
            $table->uuid('earlybank_event_id')->nullable()->unique();

            // Event classification from EB (e.g. payout.commission, payout.bonus,
            // member.status, politician.purchased).
            $table->string('event_type', 80)->index();

            // The U9itus entity that should be credited / updated.
            $table->foreignId('voter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('politician_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('citizen_id')->nullable()->constrained()->nullOnDelete();

            // The Early-bank member UUID involved (the EB referrer or the EB
            // member whose subscription status changed).
            $table->uuid('earlybank_member_id')->nullable()->index();

            // The referred U9itus entity whose activity triggered the payout.
            $table->uuid('referenced_voter_uuid')->nullable()->index();
            $table->uuid('referenced_politician_uuid')->nullable()->index();

            // Monetary breakdown. All amounts are in USD.
            $table->decimal('commission_amount', 12, 4)->default(0);
            $table->decimal('bonus_amount', 12, 4)->default(0);
            $table->decimal('payout_amount', 12, 4)->default(0);

            // Lifecycle: pending -> settled -> failed.
            $table->string('status', 40)->default('pending')->index();

            // EB's external reference for the payout / commission batch.
            $table->string('external_reference', 255)->nullable()->index();

            // Idempotency key supplied by EB or derived by us (SHA-256 of
            // event_type + earlybank_event_id + member_id + amount). Unique.
            $table->string('idempotency_key', 64)->unique();

            // Raw EB webhook payload snapshot.
            $table->json('payload');

            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            // Helpful composite indexes for summary queries.
            $table->index(['voter_id', 'status'], 'eb_earnings_voter_status_idx');
            $table->index(['politician_id', 'status'], 'eb_earnings_politician_status_idx');
            $table->index(['event_type', 'status'], 'eb_earnings_event_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('earlybank_earnings');
    }
};
