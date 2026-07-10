<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log for every outbound webhook dispatched to early-bank.com.
 *
 * Covers three event types:
 *   voter.registered  — fired at registration when earlybank_member_id is attributed
 *   voter.referred    — fired on a referred voter's FIRST completed view session ($10 bonus trigger)
 *   voter.earned      — fired on EVERY completed view session for an EB-attributed voter (10% commission)
 *
 * Delivery outcome is captured so admins can see whether EB received the signal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('earlybank_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 60)->index();            // voter.registered | voter.referred | voter.earned
            $table->uuid('voter_uuid')->nullable()->index();      // the U9itus voter involved
            $table->uuid('earlybank_member_id')->nullable()->index(); // the EB member who should be credited
            $table->uuid('view_session_uuid')->nullable();        // set for voter.referred / voter.earned
            $table->decimal('payout_amount', 10, 4)->nullable();  // voter payout that EB will commission
            $table->json('payload');                              // full outbound JSON payload snapshot
            $table->unsignedSmallInteger('http_status')->nullable(); // HTTP response status (null = exception)
            $table->string('error_message', 500)->nullable();    // exception message if delivery failed
            $table->boolean('delivered')->default(false)->index();   // true if HTTP 2xx received
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('earlybank_webhook_logs');
    }
};
