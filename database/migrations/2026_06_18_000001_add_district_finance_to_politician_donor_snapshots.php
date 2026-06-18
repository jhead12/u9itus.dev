<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('politician_donor_snapshots', function (Blueprint $table) {
            $table->string('district_raised')->nullable()->after('opensecrets_source_url');
            $table->string('district_spent')->nullable()->after('district_raised');
            $table->string('district_cash_on_hand')->nullable()->after('district_spent');
            $table->boolean('is_incumbent')->nullable()->after('district_cash_on_hand');
        });
    }

    public function down(): void
    {
        Schema::table('politician_donor_snapshots', function (Blueprint $table) {
            $table->dropColumn(['district_raised', 'district_spent', 'district_cash_on_hand', 'is_incumbent']);
        });
    }
};
