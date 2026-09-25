<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who gives to the committees linked to ballot measures, and where those committees send
 * money on.
 *
 *  - committee_donors: a committee's contributors for one calendar year, from itemized
 *    receipts (California: Form 460 Schedules A and C, plus late contribution reports
 *    filed after the latest statement). The top donors are kept, plus every donor that is
 *    itself a committee — those rows let the measure page net out money passed between
 *    linked committees, which would otherwise be counted twice.
 *  - committee_transfers: contributions a linked committee made to another committee,
 *    with the ballot measure the filing says they were for. Used to suggest committees to
 *    link and to flag a committee funding the other side.
 *  - committee_filers.late_*: contributions reported after the latest statement's period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_donors', function (Blueprint $table) {
            $table->id();
            $table->char('state', 2);
            $table->string('committee_id', 64);
            $table->unsignedSmallInteger('year');
            $table->string('donor_name', 255);
            $table->string('entity_type', 8)->nullable(); // IND|COM|OTH|PTY|SCC as filed
            $table->string('donor_committee_id', 64)->nullable(); // set when the donor is itself a committee
            $table->string('employer', 255)->nullable(); // individuals only
            $table->decimal('amount', 15, 2)->default(0); // total, including the two below
            $table->decimal('nonmonetary', 15, 2)->default(0);
            $table->decimal('late', 15, 2)->default(0); // reported after the latest statement
            $table->timestamps();

            $table->index(['state', 'committee_id', 'year']);
            $table->index(['state', 'donor_committee_id']);
        });

        Schema::create('committee_transfers', function (Blueprint $table) {
            $table->id();
            $table->char('state', 2);
            $table->string('from_committee_id', 64);
            $table->string('to_committee_id', 64);
            $table->string('to_committee_name', 255);
            $table->string('measure_reference', 255)->nullable(); // as filed, e.g. "PROPOSITION 40"
            $table->string('measure_number', 32)->nullable(); // parsed from the reference
            $table->string('measure_jurisdiction', 64)->nullable(); // as filed, e.g. "STATEWIDE"
            $table->string('position', 16)->nullable(); // support|oppose when the filing says
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('latest_on')->nullable();
            $table->timestamp('dismissed_at')->nullable(); // an admin chose not to link the recipient
            $table->timestamps();

            $table->unique(['state', 'from_committee_id', 'to_committee_id', 'measure_number'], 'committee_transfers_unique');
            $table->index(['state', 'measure_number']);
        });

        Schema::table('committee_filers', function (Blueprint $table) {
            $table->decimal('late_contributions', 15, 2)->nullable()->after('latest_filing_on');
            $table->date('late_since')->nullable()->after('late_contributions');
        });
    }

    public function down(): void
    {
        Schema::table('committee_filers', function (Blueprint $table) {
            $table->dropColumn(['late_contributions', 'late_since']);
        });
        Schema::dropIfExists('committee_transfers');
        Schema::dropIfExists('committee_donors');
    }
};
