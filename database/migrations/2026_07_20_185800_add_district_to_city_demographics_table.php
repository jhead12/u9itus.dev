<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('city_demographics', function (Blueprint $table) {
            // 'AL' for at-large single-district states, otherwise a bare number ("2").
            $table->string('district_number', 10)->nullable()->after('state');
            $table->string('district_code', 10)->nullable()->after('district_number'); // e.g. "RI-02", "VT-AL"
        });
    }

    public function down(): void
    {
        Schema::table('city_demographics', function (Blueprint $table) {
            $table->dropColumn(['district_number', 'district_code']);
        });
    }
};
