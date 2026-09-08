<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enriched, display-ready detail for a `committees` row — the read side of the
 * PAC / committee directory (/pacs/{id}). Populated by the
 * committees:enrich-profiles artisan command (nightly via GitHub Actions),
 * so page loads never touch the FEC API.
 *
 * 1:1 with `committees`. Everything here is derived from FEC:
 *   - committee detail   → /committee/{id}/
 *   - cycle totals       → /committee/{id}/totals/
 *   - independent expend. → /schedules/schedule_e/?committee_id=
 *   - top donors         → /schedules/schedule_a/?committee_id= (committee + individual)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('committee_id')->constrained()->cascadeOnDelete();
            // Denormalised so directory/detail queries can filter/lookup without
            // a join back to committees.
            $table->string('fec_committee_id', 16)->index();

            // ── Committee identity (from /committee/{id}/) ──────────────────
            $table->string('committee_type', 8)->nullable();        // FEC letter code (O, N, Q, …)
            $table->string('committee_type_full')->nullable();      // human label
            $table->string('designation', 8)->nullable();           // FEC letter code (U, B, D, …)
            $table->string('designation_full')->nullable();
            $table->string('organization_type_full')->nullable();
            $table->string('party', 8)->nullable();                 // DEM / REP / …
            $table->boolean('is_super_pac')->default(false);        // committee_type O or hybrid
            $table->boolean('is_hybrid')->default(false);           // "carey" / hybrid PAC

            $table->string('treasurer_name')->nullable();
            $table->string('street')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 2)->nullable()->index();
            $table->string('zip', 12)->nullable();
            $table->string('fec_website_url')->nullable();           // committee's own filed website, if any

            // ── Cycle financials (from /committee/{id}/totals/) ─────────────
            $table->unsignedSmallInteger('cycle')->nullable()->index();
            $table->decimal('total_receipts', 16, 2)->nullable();
            $table->decimal('total_disbursements', 16, 2)->nullable();
            $table->decimal('cash_on_hand', 16, 2)->nullable();
            $table->decimal('debts_owed', 16, 2)->nullable();
            $table->decimal('independent_expenditures', 16, 2)->nullable();
            $table->date('coverage_end_date')->nullable();

            // ── Derived JSON blobs ─────────────────────────────────────────
            // [{name, total}] — committee + individual top donors TO this committee
            $table->json('top_donors')->nullable();
            // [{politician_id?, candidate_name, state, office, support, oppose}] —
            // reverse index of who this committee spends for/against (Schedule E)
            $table->json('spending_by_race')->nullable();
            // [{candidate_name, support_oppose, amount, date, purpose}] — recent
            // Schedule E line items, newest first, capped
            $table->json('recent_expenditures')->nullable();

            $table->timestamp('enriched_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_profiles');
    }
};
