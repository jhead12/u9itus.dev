<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the voters.earlybank_member_id linkage (see
 * 2026_06_26_120000_add_earlybank_member_id_to_voters_table.php) onto
 * citizens and politicians, so registrations via an Early-bank referral
 * link are attributed regardless of which role the registrant picks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citizens', function (Blueprint $table) {
            $table->uuid('earlybank_member_id')
                ->nullable()
                ->after('referred_by_politician_id');

            $table->timestamp('earlybank_linked_at')
                ->nullable()
                ->after('earlybank_member_id');

            $table->index('earlybank_member_id', 'citizens_earlybank_member_id_idx');
        });

        Schema::table('politicians', function (Blueprint $table) {
            $table->uuid('earlybank_member_id')
                ->nullable()
                ->after('referred_by_politician_id');

            $table->timestamp('earlybank_linked_at')
                ->nullable()
                ->after('earlybank_member_id');

            $table->index('earlybank_member_id', 'politicians_earlybank_member_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('citizens', function (Blueprint $table) {
            $table->dropIndex('citizens_earlybank_member_id_idx');
            $table->dropColumn(['earlybank_member_id', 'earlybank_linked_at']);
        });

        Schema::table('politicians', function (Blueprint $table) {
            $table->dropIndex('politicians_earlybank_member_id_idx');
            $table->dropColumn(['earlybank_member_id', 'earlybank_linked_at']);
        });
    }
};
