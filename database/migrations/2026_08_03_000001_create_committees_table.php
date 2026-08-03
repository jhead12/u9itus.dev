<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A durable, deduplicated registry of FEC committee IDs seen in
 * independent-expenditure (Schedule E / "outside spending") data, built up
 * by App\Services\FECService::resolveCommitteeNames() across every
 * candidate and enrichment run. Previously a committee's name was only
 * ever resolved into the ephemeral politician_donor_snapshots.outside_spending
 * JSON blob, re-fetched (and re-resolved) from FEC on every run; this table
 * lets a name resolved once for any candidate be reused for all of them,
 * and gives future organization curation something durable to link against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committees', function (Blueprint $table) {
            $table->id();

            $table->string('fec_committee_id', 16)->unique();
            $table->string('name')->nullable();
            $table->timestamp('name_resolved_at')->nullable();

            // Manual/future link once an org is curated — never set
            // automatically; mirrors organizations.user_id's soft-link style.
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committees');
    }
};
