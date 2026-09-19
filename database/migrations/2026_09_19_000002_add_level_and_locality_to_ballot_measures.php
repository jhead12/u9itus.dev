<?php

use App\Models\BallotMeasure;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ballot_measures only knew "state + optional county", so a city bond or a school-district
 * levy could not be told apart from a statewide proposition (or from a county measure).
 * `level` says which body put it on the ballot; `locality` names the city / district when
 * that is not the county. Existing rows that carry a county are treated as county measures.
 *
 * The (state, title, election_date) unique index would stop two cities both having a
 * "Measure A" on the same ballot, so it is replaced by one that also includes `place_key`
 * (level|county|locality, maintained by the model; NOT NULL so the index still guards
 * statewide rows, which have no county or locality).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ballot_measures') || Schema::hasColumn('ballot_measures', 'level')) {
            return;
        }

        Schema::table('ballot_measures', function (Blueprint $table) {
            $table->string('level', 20)->default('state')->after('state'); // state | county | city | district
            $table->string('locality', 150)->nullable()->after('county');
            $table->string('place_key', 255)->default('');
            $table->index(['state', 'level']);
        });

        DB::table('ballot_measures')->whereNotNull('county')->where('county', '!=', '')->update(['level' => 'county']);

        DB::table('ballot_measures')->orderBy('id')->each(function ($row) {
            DB::table('ballot_measures')->where('id', $row->id)
                ->update(['place_key' => BallotMeasure::placeKeyFor($row->level, $row->county, $row->locality)]);
        });

        Schema::table('ballot_measures', function (Blueprint $table) {
            $table->unique(['state', 'title', 'election_date', 'place_key'], 'ballot_measures_place_title_date_unique');
        });
        Schema::table('ballot_measures', function (Blueprint $table) {
            $table->dropUnique('ballot_measures_state_title_date_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ballot_measures', 'level')) {
            return;
        }

        // Local measures that share a title would violate the narrower index, so keep one per (state, title, date).
        $keep = DB::table('ballot_measures')->selectRaw('MIN(id) as id')->groupBy('state', 'title', 'election_date')->pluck('id');
        DB::table('ballot_measures')->whereNotIn('id', $keep)->delete();

        Schema::table('ballot_measures', function (Blueprint $table) {
            $table->unique(['state', 'title', 'election_date'], 'ballot_measures_state_title_date_unique');
        });
        Schema::table('ballot_measures', function (Blueprint $table) {
            $table->dropUnique('ballot_measures_place_title_date_unique');
            $table->dropIndex(['state', 'level']);
            $table->dropColumn(['level', 'locality', 'place_key']);
        });
    }
};
