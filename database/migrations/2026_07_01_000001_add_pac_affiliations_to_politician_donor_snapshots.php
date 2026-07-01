<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('politician_donor_snapshots', function (Blueprint $table) {
            // Derived from top_contributors by PacAffiliationClassifier — JSON array
            // of {group, label, matched_name, total} for known advocacy-group PACs
            // (e.g. AIPAC-aligned pro-Israel PACs). Not an independently scraped source.
            $table->json('pac_affiliations')->nullable()->after('outside_spending');
        });
    }

    public function down(): void
    {
        Schema::table('politician_donor_snapshots', function (Blueprint $table) {
            $table->dropColumn('pac_affiliations');
        });
    }
};
