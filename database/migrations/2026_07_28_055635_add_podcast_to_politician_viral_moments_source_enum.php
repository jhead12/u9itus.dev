<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds 'podcast' to the source enum on both politician_viral_moments and its
 * companion viral_moment_enrichment_runs (same enum, kept in lockstep — see
 * that table's migration). Unlike users.user_type (a plain string column that
 * needed no SQLite branch), these were created via $table->enum(), which
 * SQLite enforces as a CHECK constraint — so, unlike the MySQL-only precedent
 * in add_hls_stream_media_type_to_campaigns.php, both drivers are handled
 * here so the value is actually usable in local/test SQLite, not just MySQL.
 */
return new class extends Migration
{
    private const OLD = ['youtube', 'cspan', 'news', 'tiktok', 'instagram', 'x'];

    private const NEW = ['youtube', 'cspan', 'news', 'tiktok', 'instagram', 'x', 'podcast'];

    public function up(): void
    {
        $this->setEnum('politician_viral_moments', self::NEW);
        $this->setEnum('viral_moment_enrichment_runs', self::NEW);
    }

    public function down(): void
    {
        $this->setEnum('politician_viral_moments', self::OLD);
        $this->setEnum('viral_moment_enrichment_runs', self::OLD);
    }

    /**
     * @param  list<string>  $values
     */
    private function setEnum(string $table, array $values): void
    {
        if (DB::getDriverName() === 'mysql') {
            $list = "'" . implode("','", $values) . "'";
            DB::statement("ALTER TABLE {$table} MODIFY source ENUM({$list}) NOT NULL");

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($values) {
            $blueprint->enum('source', $values)->change();
        });
    }
};
