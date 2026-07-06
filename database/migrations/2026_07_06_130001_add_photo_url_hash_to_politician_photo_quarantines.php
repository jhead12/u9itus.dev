<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('politician_photo_quarantines')) {
            return;
        }

        if (! Schema::hasColumn('politician_photo_quarantines', 'photo_url_hash')) {
            Schema::table('politician_photo_quarantines', function (Blueprint $table) {
                $table->char('photo_url_hash', 64)->nullable()->after('photo_url');
            });
        }

        DB::table('politician_photo_quarantines')
            ->where(function ($q) {
                $q->whereNull('photo_url_hash')->orWhere('photo_url_hash', '');
            })
            ->orderBy('id')
            ->select(['id', 'photo_url'])
            ->chunkById(250, function ($rows) {
                foreach ($rows as $row) {
                    $hash = hash('sha256', strtolower(trim((string) $row->photo_url)));
                    DB::table('politician_photo_quarantines')
                        ->where('id', $row->id)
                        ->update(['photo_url_hash' => $hash]);
                }
            });

        try {
            DB::statement('ALTER TABLE politician_photo_quarantines MODIFY photo_url_hash CHAR(64) NOT NULL');
        } catch (\Throwable) {
            // Ignore when engine/version already has compatible definition.
        }

        try {
            DB::statement('CREATE UNIQUE INDEX politician_photo_quarantines_unique_photo_hash ON politician_photo_quarantines (politician_id, photo_url_hash)');
        } catch (\Throwable) {
            // Ignore when index already exists.
        }

        // Drop legacy long-url unique key when present to avoid oversized-index issues.
        try {
            DB::statement('DROP INDEX politician_photo_quarantines_politician_id_photo_url_unique ON politician_photo_quarantines');
        } catch (\Throwable) {
            // Ignore when old index does not exist.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('politician_photo_quarantines')) {
            return;
        }

        try {
            DB::statement('DROP INDEX politician_photo_quarantines_unique_photo_hash ON politician_photo_quarantines');
        } catch (\Throwable) {
            // Ignore when index does not exist.
        }

        if (Schema::hasColumn('politician_photo_quarantines', 'photo_url_hash')) {
            Schema::table('politician_photo_quarantines', function (Blueprint $table) {
                $table->dropColumn('photo_url_hash');
            });
        }
    }
};
