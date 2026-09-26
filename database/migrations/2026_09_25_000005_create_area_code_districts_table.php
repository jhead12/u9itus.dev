<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Area code → city → congressional district, so /district-lookup can turn a
 * bare area code into a list of cities the voter picks from. Cities come from
 * libphonenumber's prefix geocoding data, districts from the Census place and
 * county-subdivision relationship files. Filled by geo:sync-area-code-districts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('area_code_districts', function (Blueprint $table) {
            $table->id();
            $table->char('area_code', 3);
            $table->char('state', 2);
            $table->string('city', 100);
            $table->string('district_number', 4); // "1".."52", or "AL" for at-large / delegate seats
            $table->unsignedBigInteger('land_area')->default(0); // square metres of the city inside the district
            $table->unsignedSmallInteger('congress')->default(119);

            $table->unique(['area_code', 'state', 'city', 'district_number']);
            $table->index('area_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area_code_districts');
    }
};
