<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the voter's / politician's OWN Early-bank membership identifier.
 *
 * This is distinct from earlybank_member_id (already on voters), which stores
 * the UUID of the Early-bank MEMBER who referred this user.
 *
 * earlybank_own_member_uuid is populated by the POST /api/v1/earlybank/member-enrolled
 * inbound webhook, fired by earlybank.com when a U9itus user joins as an EB member.
 * Once set, the U9itus referrals page shows the user's personal EB referral link
 * and dashboard link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            if (! Schema::hasColumn('voters', 'earlybank_own_member_uuid')) {
                $table->uuid('earlybank_own_member_uuid')
                    ->nullable()
                    ->after('earlybank_linked_at')
                    ->comment('The voter\'s own Early-bank member UUID (set via member-enrolled webhook).');

                $table->timestamp('earlybank_own_linked_at')
                    ->nullable()
                    ->after('earlybank_own_member_uuid')
                    ->comment('When the voter joined Early-bank as a member.');

                $table->index('earlybank_own_member_uuid', 'voters_eb_own_member_uuid_idx');
            }
        });

        Schema::table('politicians', function (Blueprint $table) {
            if (! Schema::hasColumn('politicians', 'earlybank_own_member_uuid')) {
                $table->uuid('earlybank_own_member_uuid')
                    ->nullable()
                    ->comment('The politician\'s own Early-bank member UUID (set via member-enrolled webhook).');

                $table->timestamp('earlybank_own_linked_at')
                    ->nullable()
                    ->comment('When the politician joined Early-bank as a member.');

                $table->index('earlybank_own_member_uuid', 'politicians_eb_own_member_uuid_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->dropIndex('voters_eb_own_member_uuid_idx');
            $table->dropColumn(['earlybank_own_member_uuid', 'earlybank_own_linked_at']);
        });

        Schema::table('politicians', function (Blueprint $table) {
            $table->dropIndex('politicians_eb_own_member_uuid_idx');
            $table->dropColumn(['earlybank_own_member_uuid', 'earlybank_own_linked_at']);
        });
    }
};
