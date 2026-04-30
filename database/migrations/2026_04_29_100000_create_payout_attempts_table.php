<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('voter_id')->index();

            // Deterministic SHA-256 hash of voter_id + sorted session IDs.
            // Unique constraint prevents duplicate attempts for the same session set.
            $table->string('idempotency_key', 64)->unique();

            $table->string('processor', 20)->default('stripe'); // stripe|paypal|cashapp

            // pending   → attempt row created, external call not yet made
            // submitted → external call succeeded, awaiting confirmation/webhook
            // paid      → confirmed paid (Stripe instant) or reconciled (PayPal)
            // failed    → external call failed or payout was rejected
            $table->string('status', 20)->default('pending')->index();

            $table->decimal('amount', 12, 2);

            // Stripe transfer ID / PayPal batch_id / CashApp reference
            $table->string('processor_reference', 255)->nullable()->index();

            // JSON array of view_session IDs covered by this attempt
            $table->json('session_ids');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_attempts');
    }
};
