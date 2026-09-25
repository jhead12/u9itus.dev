<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every measure a filing declares, not just one. A California cover page names one; a
 * Texas committee-purpose sheet can list several ("Prop A" through "Prop D" of one school
 * district, or "Amendments 2, 5-10"). Each entry: number, position, election_date,
 * description. See CommitteeFinanceSnapshot::declaredMeasures().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('committee_finance_snapshots', function (Blueprint $table) {
            $table->json('declared_measures')->nullable()->after('declared_position');
        });
    }

    public function down(): void
    {
        Schema::table('committee_finance_snapshots', function (Blueprint $table) {
            $table->dropColumn('declared_measures');
        });
    }
};
