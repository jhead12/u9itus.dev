<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per campaign finance filing (latest amendment) for a committee linked to a ballot
 * measure. Keyed by the state's filer ID rather than the link, since one committee can be
 * linked to several measures. Amounts come from the filing's summary page (Form 460 in
 * California); the declared_* columns are what the committee told the state it supports or
 * opposes, which MeasureCommitteeRules checks each link against.
 *
 * committee_filers records, per linked filer ID, whether the import found it at all and the
 * name it files under — so a mistyped ID or a stale committee is flagged for review.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_finance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->char('state', 2);
            $table->string('committee_id', 64);
            $table->string('source', 32); // cal-access
            $table->string('filing_id', 32);
            $table->unsignedSmallInteger('amend_id')->default(0);
            $table->string('form_type', 16);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->date('filed_on')->nullable();
            // Summary page. _period = this filing's period (column A); _ytd = calendar year to date (column B).
            $table->decimal('contributions_period', 15, 2)->nullable();
            $table->decimal('contributions_ytd', 15, 2)->nullable();
            $table->decimal('nonmonetary_ytd', 15, 2)->nullable();
            $table->decimal('expenditures_ytd', 15, 2)->nullable();
            $table->decimal('cash_on_hand', 15, 2)->nullable();
            $table->string('declared_measure_number', 32)->nullable();
            $table->string('declared_measure_name', 255)->nullable();
            $table->string('declared_position', 16)->nullable(); // support|oppose
            $table->timestamps();

            $table->unique(['state', 'committee_id', 'filing_id'], 'committee_finance_snapshots_unique');
            $table->index(['state', 'committee_id', 'period_end']);
        });

        Schema::create('committee_filers', function (Blueprint $table) {
            $table->id();
            $table->char('state', 2);
            $table->string('committee_id', 64);
            $table->string('source', 32);
            $table->boolean('found')->default(false); // any filing under this ID in the state's data
            $table->string('filer_name', 255)->nullable(); // as it appears on the latest filing
            $table->date('latest_filing_on')->nullable(); // any form, incl. late contribution reports
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['state', 'committee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_filers');
        Schema::dropIfExists('committee_finance_snapshots');
    }
};
