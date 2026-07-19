<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the Early-bank `member-enrolled` Stripe Connect / payouts state that
 * Early-bank.com POSTs to /api/v1/earlybank/member-enrolled when a U9itus user
 * joins as a paying member.
 *
 *   earlybank_payouts_enabled              — member cleared for payouts on EB
 *   earlybank_stripe_connect_account_id    — the EB member's Stripe Connect
 *                                            account id (distinct from the
 *                                            voter's own stripe_account_id)
 *   earlybank_stripe_connect_onboarding_complete — onboarding finished
 *
 * Voters and politicians already carry earlybank_own_member_uuid +
 * earlybank_own_linked_at (migration 2026_07_05_000001); citizens do not — so
 * citizens receive all five earlybank columns here, which also unblocks citizen
 * enrollment (previously memberEnrolled routed `citizen` to the voters table and
 * 404'd).
 *
 * Idempotent: guarded with Schema::hasColumn so it is safe to re-run.
 */
return new class extends Migration
{
    private function addStripeStateColumns(string $table): void
    {
        Schema::table($table, function (Blueprint $t) {
            if (! Schema::hasColumn($t->getTable(), 'earlybank_payouts_enabled')) {
                $t->boolean('earlybank_payouts_enabled')->default(false);
            }
            if (! Schema::hasColumn($t->getTable(), 'earlybank_stripe_connect_account_id')) {
                $t->string('earlybank_stripe_connect_account_id')->nullable();
            }
            if (! Schema::hasColumn($t->getTable(), 'earlybank_stripe_connect_onboarding_complete')) {
                $t->boolean('earlybank_stripe_connect_onboarding_complete')->default(false);
            }
        });
    }

    private function dropStripeStateColumns(string $table): void
    {
        Schema::table($table, function (Blueprint $t) {
            foreach ([
                'earlybank_payouts_enabled',
                'earlybank_stripe_connect_account_id',
                'earlybank_stripe_connect_onboarding_complete',
            ] as $column) {
                if (Schema::hasColumn($t->getTable(), $column)) {
                    $t->dropColumn($column);
                }
            }
        });
    }

    public function up(): void
    {
        // Voters + politicians: add the three new state columns alongside the
        // existing earlybank_own_member_uuid / earlybank_own_linked_at.
        $this->addStripeStateColumns('voters');
        $this->addStripeStateColumns('politicians');

        // Citizens: no earlybank columns exist yet — add the full set.
        Schema::table('citizens', function (Blueprint $t) {
            if (! Schema::hasColumn('citizens', 'earlybank_own_member_uuid')) {
                $t->uuid('earlybank_own_member_uuid')->nullable()
                    ->comment('The citizen\'s own Early-bank member UUID (set via member-enrolled webhook).');
                $t->timestamp('earlybank_own_linked_at')->nullable()
                    ->comment('When the citizen joined Early-bank as a member.');
                $t->index('earlybank_own_member_uuid', 'citizens_eb_own_member_uuid_idx');
            }
        });
        $this->addStripeStateColumns('citizens');
    }

    public function down(): void
    {
        $this->dropStripeStateColumns('citizens');

        Schema::table('citizens', function (Blueprint $t) {
            if (Schema::hasColumn('citizens', 'earlybank_own_member_uuid')) {
                $t->dropIndex('citizens_eb_own_member_uuid_idx');
                $t->dropColumn(['earlybank_own_member_uuid', 'earlybank_own_linked_at']);
            }
        });

        $this->dropStripeStateColumns('politicians');
        $this->dropStripeStateColumns('voters');
    }
};