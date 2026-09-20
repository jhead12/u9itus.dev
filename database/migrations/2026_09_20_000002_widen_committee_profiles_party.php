<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FECService::getCommitteeDetail() stores FEC's `party_full` (e.g.
 * "DEMOCRATIC PARTY", "DEMOCRATIC-FARMER-LABOR") in `party`, which was created
 * as varchar(8) on the assumption it would hold DEM/REP codes. The nightly
 * committees:enrich-profiles run failed with "Data too long for column
 * 'party'" for any committee with a full party name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('committee_profiles', function (Blueprint $table) {
            $table->string('party', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('committee_profiles', function (Blueprint $table) {
            $table->string('party', 8)->nullable()->change();
        });
    }
};
