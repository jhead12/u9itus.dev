<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Collapses exact duplicate ballot measures (same state + title +
     * election date — e.g. two identical "Prop 5 — Bond for Schools" rows
     * for CA, created by an un-deduped admin form double-submit) before
     * adding a unique index that stops it from happening again.
     */
    public function up(): void
    {
        $rows = DB::table('ballot_measures')->orderBy('id')->get(['id', 'state', 'title', 'election_date']);

        $seen = [];
        $duplicateIds = [];
        foreach ($rows as $row) {
            // election_date is stored as a datetime; compare by calendar day
            // only, matching whereDate() usage in ImportBallotMeasures.
            $dateKey = $row->election_date ? substr((string) $row->election_date, 0, 10) : null;
            $key = strtoupper((string) $row->state) . '|' . mb_strtolower(trim((string) $row->title)) . '|' . $dateKey;

            if (isset($seen[$key])) {
                $duplicateIds[] = $row->id;
            } else {
                $seen[$key] = $row->id;
            }
        }

        if ($duplicateIds) {
            DB::table('ballot_measures')->whereIn('id', $duplicateIds)->delete();
        }

        Schema::table('ballot_measures', function (Blueprint $table) {
            // NULL election_date rows aren't caught by this constraint —
            // MySQL and SQLite both treat NULL as distinct from NULL in a
            // unique index. The write paths (AdminBallotMeasureController::
            // store, ImportBallotMeasures) already dedupe that case
            // explicitly via whereNull('election_date') before inserting.
            $table->unique(['state', 'title', 'election_date'], 'ballot_measures_state_title_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ballot_measures', function (Blueprint $table) {
            $table->dropUnique('ballot_measures_state_title_date_unique');
        });
    }
};
