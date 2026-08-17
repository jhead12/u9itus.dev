<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anchors the content-driven boundary-digest cadence (see SendBoundaryDigest):
 * each voter's next email covers everything new since THEIR last send, not a
 * fixed calendar week, and eligibility is decided per-voter (floor: 7 days
 * since last send; burst: 2+ days plus enough new content) — set on both
 * real and pending (guest) voters, since both share this one email.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->timestamp('last_digest_sent_at')->nullable()->after('digest_confirmation_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->dropColumn('last_digest_sent_at');
        });
    }
};
