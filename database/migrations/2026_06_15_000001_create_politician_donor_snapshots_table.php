<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('politician_donor_snapshots')) {
            return;
        }

        Schema::create('politician_donor_snapshots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('politician_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            // Top individual donors (JSON array of {name, total, employer, state})
            $table->json('top_contributors')->nullable();

            // Top funding industries (JSON array of {industry_name, total, pct})
            $table->json('top_industries')->nullable();

            // FEC filing summary (JSON: total_receipts, total_disbursements, cash_on_hand_end_period)
            $table->json('fec_summary')->nullable();

            // Outside spending / PAC support (JSON array of {committee_name, total, support_oppose})
            $table->json('outside_spending')->nullable();

            // Source attribution URLs for each provider
            $table->string('opensecrets_source_url', 512)->nullable();
            $table->string('fec_source_url', 512)->nullable();

            // Cycle year the data was fetched for (e.g. 2024)
            $table->unsignedSmallInteger('election_cycle')->nullable()->index();

            // When data was last successfully fetched
            $table->timestamp('enriched_at')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('politician_donor_snapshots');
    }
};
