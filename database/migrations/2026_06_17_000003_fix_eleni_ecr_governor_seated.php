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
        // Use query builder to stay compatible with SQLite (tests) and MySQL (production).
        // We can't use JSON_EXTRACT in a cross-driver way, so match via LIKE on payload text.
        DB::table('election_candidate_records')
            ->whereRaw("LOWER(full_name) = 'eleni kounalakis'")
            ->where('state', 'CA')
            ->whereRaw("LOWER(COALESCE(political_office,'')) = 'governor'")
            ->where(function ($q) {
                $q->where('payload', 'like', '%"status":"seated"%')
                  ->orWhere('payload', 'like', '%"status": "seated"%');
            })
            ->update([
                'political_office' => 'Lieutenant Governor',
                'updated_at'       => now(),
            ]);

        // Also fix Politician rows
        DB::table('politicians')
            ->whereRaw("LOWER(full_name) = 'eleni kounalakis'")
            ->where('state', 'CA')
            ->whereRaw("LOWER(COALESCE(political_office,'')) = 'governor'")
            ->where('term_status', 'seated')
            ->update([
                'political_office' => 'Lieutenant Governor',
                'updated_at'       => now(),
            ]);

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
