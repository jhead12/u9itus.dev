<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the Early-bank.com member linkage to the voters table.
 *
 * When a voter registers via an Early-bank referral link, the Early-bank
 * member's UUID is stored here so that:
 *   1. View-session earnings can be attributed back to the referring member
 *      (10% commission + $10 flat join bonus, owned by Early-bank).
 *   2. The outbound webhook dispatcher knows which sessions to forward.
 *
 * The column is nullable — most voters will not have an Early-bank referrer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->uuid('earlybank_member_id')
                ->nullable()
                ->after('referred_by_politician_id');

            $table->timestamp('earlybank_linked_at')
                ->nullable()
                ->after('earlybank_member_id');

            $table->index('earlybank_member_id', 'voters_earlybank_member_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->dropIndex('voters_earlybank_member_id_idx');
            $table->dropColumn(['earlybank_member_id', 'earlybank_linked_at']);
        });
    }
};
