<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores US Census Bureau population figures at the state and
 * congressional-district level. Data is sourced from the Census Bureau
 * Decennial / ACS APIs via `php artisan geo:sync-census-population`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('district_populations', function (Blueprint $table) {
            $table->id();

            // Two-letter USPS abbreviation, e.g. "CA". NULL = national total.
            $table->char('state', 2)->nullable()->index();

            // FIPS numeric district number, e.g. 33. NULL = state-level row.
            $table->unsignedSmallInteger('district_number')->nullable();

            // Human-readable label, e.g. "CA-33" or "California"
            $table->string('label', 60)->nullable();

            // Total resident population from Census source
            $table->unsignedBigInteger('total_population');

            // Voting-age population (18+) when available
            $table->unsignedBigInteger('voting_age_population')->nullable();

            // Census product year (2020, 2022, 2024 …)
            $table->unsignedSmallInteger('census_year');

            // Source product code, e.g. "dec/pl" or "acs/acs5"
            $table->string('census_product', 30)->default('dec/pl');

            $table->timestamps();

            // One row per state + district + census_year
            $table->unique(['state', 'district_number', 'census_year'], 'district_pop_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('district_populations');
    }
};
