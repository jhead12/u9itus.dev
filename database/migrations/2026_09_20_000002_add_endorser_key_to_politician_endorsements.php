<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * politician_endorsements used to hold one row per (politician, office group), so two
 * senators endorsing the same candidate collapsed into one "U.S. Senator Endorsed" chip
 * and the endorser was never really named. The profile now lists who endorsed, so the
 * row is per endorser: `endorser_key` is the slug of the endorser's name ('' when the
 * coverage only says "the governor").
 *
 * The old endorser_name values came from a looser extractor and include words like
 * "Republican" or a bill title, so they are cleared here; candidates:detect-endorsements
 * re-derives them from the stored articles.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('politician_endorsements') || Schema::hasColumn('politician_endorsements', 'endorser_key')) {
            return;
        }

        Schema::table('politician_endorsements', function (Blueprint $table) {
            $table->string('endorser_key', 120)->default('')->after('label');
        });

        // New index first: MySQL will not drop the old unique while it is the only index
        // backing the politician_id foreign key.
        Schema::table('politician_endorsements', function (Blueprint $table) {
            $table->unique(['politician_id', 'group_key', 'endorser_key'], 'politician_endorsements_endorser_unique');
        });
        Schema::table('politician_endorsements', function (Blueprint $table) {
            $table->dropUnique('politician_endorsements_unique');
        });

        DB::table('politician_endorsements')->update(['endorser_name' => null]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('politician_endorsements', 'endorser_key')) {
            return;
        }

        // Collapse back to one row per group before restoring the narrower unique index.
        $keep = DB::table('politician_endorsements')
            ->selectRaw('MIN(id) as id')
            ->groupBy('politician_id', 'group_key')
            ->pluck('id');
        DB::table('politician_endorsements')->whereNotIn('id', $keep)->delete();

        Schema::table('politician_endorsements', function (Blueprint $table) {
            $table->unique(['politician_id', 'group_key'], 'politician_endorsements_unique');
        });
        Schema::table('politician_endorsements', function (Blueprint $table) {
            $table->dropUnique('politician_endorsements_endorser_unique');
            $table->dropColumn('endorser_key');
        });
    }
};
