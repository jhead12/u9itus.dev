<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a campaign committee (as registered in a state's campaign finance system) to the
 * ballot measure it supports or opposes. Committee names rarely say which measure they are
 * for ("Building a Better California" opposes Prop 40), so every link is a claim a human
 * checks against the filing: only `verified` rows are shown to voters.
 *
 * Integrity: MeasureCommitteeRules runs in the model's saving hook (hard rules) and in
 * ballot-measures:audit-committee-links (soft flags, FMEA priority scoring, run metrics
 * feeding politicians:check-cleanup-health).
 *
 * Also adds campaign_finance_url to election_data_sources — the official filing site for a
 * jurisdiction, shown as "View official filings" and used to check a link's evidence URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ballot_measure_committees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ballot_measure_id')->constrained()->cascadeOnDelete();
            $table->char('state', 2); // whose filing system committee_id belongs to; must match the measure
            $table->string('committee_id', 64); // the state's filer ID, e.g. Cal-Access 1486767
            $table->string('committee_name', 255);
            $table->string('position', 16); // support|oppose
            $table->string('source_url', 2048); // the filing or committee page that shows the position
            $table->string('status', 16)->default('pending'); // pending|verified|rejected
            $table->json('integrity_flags')->nullable(); // soft flags found by the last check
            $table->json('acknowledged_flags')->nullable(); // flags a reviewer saw and accepted when verifying
            $table->unsignedTinyInteger('severity')->nullable();
            $table->unsignedTinyInteger('occurrence')->nullable();
            $table->unsignedTinyInteger('detectability')->nullable();
            $table->unsignedSmallInteger('priority_score')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            // One committee takes one side of a measure — never both.
            $table->unique(['ballot_measure_id', 'state', 'committee_id'], 'ballot_measure_committees_unique');
            $table->index(['status', 'priority_score']);
        });

        Schema::table('election_data_sources', function (Blueprint $table) {
            $table->string('campaign_finance_url', 2048)->nullable()->after('ballotpedia_url');
        });
    }

    public function down(): void
    {
        Schema::table('election_data_sources', function (Blueprint $table) {
            $table->dropColumn('campaign_finance_url');
        });

        Schema::dropIfExists('ballot_measure_committees');
    }
};
