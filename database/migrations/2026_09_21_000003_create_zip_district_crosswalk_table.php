<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ZIP (ZCTA) → congressional district crosswalk from the Census Bureau's
 * relationship file. Google Civic stopped returning congressional districts
 * for ZIP-only input, so this is what lets the map and /district-lookup turn a
 * bare ZIP into the district(s) it touches. Filled by geo:sync-zip-districts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zip_district_crosswalk', function (Blueprint $table) {
            $table->id();
            $table->char('zip', 5);
            $table->char('state', 2);
            $table->string('district_number', 4); // "1".."52", or "AL" for at-large / delegate seats
            $table->unsignedBigInteger('land_area')->default(0); // square metres of the ZIP inside the district
            $table->unsignedSmallInteger('congress')->default(119);

            $table->unique(['zip', 'state', 'district_number']);
            $table->index('zip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zip_district_crosswalk');
    }
};
