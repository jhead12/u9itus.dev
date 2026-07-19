<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds earlybank_subscription_status to voters, politicians, and citizens.
 *
 * Synced by Early-bank.com via the member.status inbound webhook event.
 * Distinct from earlybank_payouts_enabled (which tracks payout capability);
 * this column tracks the membership tier (inactive / free / paid / suspended).
 */
return new class extends Migration
{
    private function addStatusColumn(string $table): void
    {
        Schema::table($table, function (Blueprint $t) {
            if (! Schema::hasColumn($t->getTable(), 'earlybank_subscription_status')) {
                $t->string('earlybank_subscription_status', 40)
                    ->nullable()
                    ->after('earlybank_payouts_enabled')
                    ->index();
            }
        });
    }

    private function dropStatusColumn(string $table): void
    {
        Schema::table($table, function (Blueprint $t) {
            if (Schema::hasColumn($t->getTable(), 'earlybank_subscription_status')) {
                $t->dropColumn('earlybank_subscription_status');
            }
        });
    }

    public function up(): void
    {
        $this->addStatusColumn('voters');
        $this->addStatusColumn('politicians');
        $this->addStatusColumn('citizens');
    }

    public function down(): void
    {
        $this->dropStatusColumn('citizens');
        $this->dropStatusColumn('politicians');
        $this->dropStatusColumn('voters');
    }
};
