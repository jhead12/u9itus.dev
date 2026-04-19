<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores structured "about this office" data for each politician.
 * Exposed publicly to voters as an informational popup while watching
 * video campaigns — no PII, purely civic education data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('politician_office_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('politician_id')
                  ->unique()
                  ->constrained('politicians')
                  ->cascadeOnDelete();

            // ── Office identity ─────────────────────────────────────────
            $table->string('office_title', 120)
                  ->comment('Official title of the position, e.g. "U.S. Senator", "City Council Member"');
            $table->string('governance_level', 60)->nullable()
                  ->comment('federal | state | county | city | school_board | special_district');
            $table->string('jurisdiction', 120)->nullable()
                  ->comment('Human-readable scope, e.g. "State of California" or "City of Oakland"');
            $table->string('how_elected_or_appointed', 80)->nullable()
                  ->comment('elected | appointed | retained');
            $table->unsignedTinyInteger('term_length_years')->nullable()
                  ->comment('Length of one term in years');
            $table->unsignedSmallInteger('seats_in_body')->nullable()
                  ->comment('Total number of seats in the governing body, if applicable');

            // ── Compensation ─────────────────────────────────────────────
            $table->unsignedInteger('annual_salary_min')->nullable()
                  ->comment('Low end of salary range in USD cents (stores e.g. 17400000 for $174,000)');
            $table->unsignedInteger('annual_salary_max')->nullable()
                  ->comment('High end of salary range in USD cents');
            $table->string('salary_currency', 3)->default('USD');
            $table->string('salary_source_note', 255)->nullable()
                  ->comment('Citation or note explaining the salary figure, e.g. "Per Congressional Research Service, 2024"');

            // ── Civic description (shown to voters) ──────────────────────
            $table->text('role_summary')->nullable()
                  ->comment('Plain-language 1–3 sentence summary of what this official does day-to-day');
            $table->text('community_impact')->nullable()
                  ->comment('How this office directly affects residents\' lives');
            $table->json('key_duties')->nullable()
                  ->comment('JSON array of strings listing specific duties/responsibilities');
            $table->json('powers_and_limits')->nullable()
                  ->comment('JSON array: what the office can and cannot do');

            // ── Source & audit ────────────────────────────────────────────
            $table->string('source_url', 512)->nullable()
                  ->comment('Official government or verified reference URL for this office info');
            $table->boolean('is_verified')->default(false)
                  ->comment('True once an admin has confirmed the data accuracy');
            $table->timestamp('data_verified_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('politician_office_profiles');
    }
};
