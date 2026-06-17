<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Targeted fix: Eleni Kounalakis appears as "Seated" under Governor on the map.
 * An election_candidate_records row has political_office=Governor AND payload
 * containing status=seated. Move that row to Lieutenant Governor.
 *
 * Also ensure her proper seated Lt. Gov record exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Use MySQL JSON_UNQUOTE/JSON_EXTRACT to match regardless of exact PHP structure
        // Also cover plain LIKE match in case payload is stored as text
        DB::statement("
            UPDATE election_candidate_records
            SET political_office = 'Lieutenant Governor',
                updated_at = NOW()
            WHERE LOWER(full_name) = 'eleni kounalakis'
              AND state = 'CA'
              AND LOWER(COALESCE(political_office,'')) = 'governor'
              AND (
                    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.status')) = 'seated'
                    OR payload LIKE '%\"status\":\"seated\"%'
                    OR payload LIKE '%\"status\": \"seated\"%'
              )
        ");

        // Also fix Politician rows
        DB::statement("
            UPDATE politicians
            SET political_office = 'Lieutenant Governor',
                updated_at = NOW()
            WHERE LOWER(full_name) = 'eleni kounalakis'
              AND state = 'CA'
              AND LOWER(COALESCE(political_office,'')) = 'governor'
              AND term_status = 'seated'
        ");

        // Upsert a clean seated Lt. Gov ECR row
        $ltGovExists = DB::table('election_candidate_records')
            ->whereRaw("LOWER(full_name) = 'eleni kounalakis'")
            ->where('state', 'CA')
            ->whereRaw("LOWER(COALESCE(political_office,'')) = 'lieutenant governor'")
            ->exists();

        if (! $ltGovExists) {
            DB::table('election_candidate_records')->insert([
                'full_name'             => 'Eleni Kounalakis',
                'political_office'      => 'Lieutenant Governor',
                'governance_level'      => 'state',
                'state'                 => 'CA',
                'district'             => 'Statewide',
                'party_affiliation'     => 'Democratic',
                'source'                => 'manual_correction',
                'external_candidate_id' => 'ca-ltgov-eleni-kounalakis',
                'payload'               => json_encode([
                    'status'    => 'seated',
                    'term_end'  => 'January 2027',
                    'term_note' => 'Term-limited · running for Governor 2026',
                ]),
                'last_seen_at' => now(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        } else {
            DB::table('election_candidate_records')
                ->whereRaw("LOWER(full_name) = 'eleni kounalakis'")
                ->where('state', 'CA')
                ->whereRaw("LOWER(COALESCE(political_office,'')) = 'lieutenant governor'")
                ->update([
                    'payload'    => json_encode([
                        'status'    => 'seated',
                        'term_end'  => 'January 2027',
                        'term_note' => 'Term-limited · running for Governor 2026',
                    ]),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void {}
};
