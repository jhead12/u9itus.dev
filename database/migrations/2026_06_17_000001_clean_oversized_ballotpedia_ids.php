<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Heal any oversized ballotpedia_id values that were inserted before the
 * SQLSTATE[22001] truncation fix (commit fce86a35).
 *
 * Rows where ballotpedia_id contains '?' (survey/mailto URLs) or exceeds
 * 255 characters are cleared to NULL.  The column definition is left
 * unchanged — it is already VARCHAR(255) nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        // NULL out any rows where the stored value looks like a survey URL
        // (contains '?'), a mailto/tel scheme (contains ':'), or is an
        // election index page (contains 'election' followed by ',_').
        // Also NULL out any value that somehow exceeds 255 chars.
        DB::statement("
            UPDATE politicians
            SET ballotpedia_id = NULL
            WHERE ballotpedia_id IS NOT NULL
              AND (
                    ballotpedia_id LIKE '%?%'
                 OR ballotpedia_id LIKE '%:%'
                 OR ballotpedia_id REGEXP 'elections?[,_]'
                 OR CHAR_LENGTH(ballotpedia_id) > 255
              )
        ");
    }

    public function down(): void
    {
        // Data cleanup — cannot be reversed.
    }
};
