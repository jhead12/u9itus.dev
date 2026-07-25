<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('politician_donor_snapshots', function (Blueprint $table) {
            // OpenSecrets candidate summary scraped from the profile page:
            // {total_raised, total_spent, cash_on_hand, debt}. The only finance
            // totals source for non-federal (state/local) candidates. Scraped by
            // OpenSecretsService::fetchCampaignFinanceData() but previously
            // discarded by EnrichPoliticianDonors — now persisted for display.
            $table->json('opensecrets_summary')->nullable()->after('fec_summary');
        });
    }

    public function down(): void
    {
        Schema::table('politician_donor_snapshots', function (Blueprint $table) {
            $table->dropColumn('opensecrets_summary');
        });
    }
};