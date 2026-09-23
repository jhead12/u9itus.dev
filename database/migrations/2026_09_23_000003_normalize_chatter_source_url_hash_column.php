<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * source_url_hash started life as a MySQL "GENERATED ALWAYS AS (SHA2(...))
 * STORED" column, then moved to a plain column computed in PHP
 * (PoliticianChatterItem::booted()) so the same migration works against the
 * SQLite test database, which has no SHA2 function. That leaves two states
 * this needs to reconcile on any environment that already has the table:
 *   - no source_url_hash column at all (envs migrated before the hash existed)
 *   - a MySQL generated column (envs that ran the storedAs() version) — a
 *     generated column can't be written to directly, so Eloquent's explicit
 *     assignment would fail against it going forward.
 * Both are converted to a plain, backfilled, NOT NULL column. A no-op
 * everywhere else (fresh test/staging DBs already get the plain column from
 * 2026_09_22_000001).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('politician_chatter_items')) {
            return;
        }

        if (! Schema::hasColumn('politician_chatter_items', 'source_url_hash')) {
            Schema::table('politician_chatter_items', function (Blueprint $table) {
                $table->string('source_url_hash', 64)->nullable()->after('source_url');
            });
            $this->backfill();
            Schema::table('politician_chatter_items', function (Blueprint $table) {
                $table->string('source_url_hash', 64)->nullable(false)->change();
            });
        } elseif ($this->isGeneratedColumn()) {
            DB::statement('ALTER TABLE politician_chatter_items MODIFY source_url_hash VARCHAR(64) NOT NULL');
        }

        $existingIndexes = DB::getDriverName() === 'mysql'
            ? collect(DB::select('SHOW INDEX FROM politician_chatter_items'))->pluck('Key_name')->unique()
            : collect();
        if (! $existingIndexes->contains('politician_chatter_items_politician_id_source_url_hash_unique')) {
            try {
                Schema::table('politician_chatter_items', function (Blueprint $table) {
                    $table->unique(['politician_id', 'source_url_hash']);
                });
            } catch (\Throwable $e) {
                // Already present under a different name (e.g. SQLite auto-naming) — fine.
            }
        }
    }

    private function backfill(): void
    {
        DB::table('politician_chatter_items')->orderBy('id')->each(function ($row) {
            DB::table('politician_chatter_items')->where('id', $row->id)
                ->update(['source_url_hash' => hash('sha256', $row->source_url)]);
        });
    }

    private function isGeneratedColumn(): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'politician_chatter_items')
            ->where('COLUMN_NAME', 'source_url_hash')
            ->where('EXTRA', 'like', '%GENERATED%')
            ->exists();
    }

    public function down(): void
    {
        // Original generated-column form is no longer used by the app; nothing to revert to.
    }
};
