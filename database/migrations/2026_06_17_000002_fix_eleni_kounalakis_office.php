<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data correction: Eleni Kounalakis is the seated California Lieutenant Governor
 * (term ends Jan 2027). She is also running for Governor in 2026.
 *
 * The scraper only imported her as a Governor candidate. This migration:
 *  1. Ensures her existing ECR row is kept as Governor candidate (correct — she IS running)
 *  2. Ensures a *separate* seated Politician row exists for her Lt. Gov role,
 *     so the map panel shows her in both the Lieutenant Governor (seated) section
 *     AND the Governor (running) section.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Fix ElectionCandidateRecord rows that have her as Governor + seated in payload.
        // These cause her to show up under "Current Officeholder" in the Governor section.
        // Correct the office to Lieutenant Governor on those rows.
        $ecrRows = DB::table('election_candidate_records')
            ->whereRaw("LOWER(full_name) = 'eleni kounalakis'")
            ->where('state', 'CA')
            ->whereRaw("LOWER(COALESCE(political_office,'')) = 'governor'")
            ->get(['id', 'payload']);

        foreach ($ecrRows as $row) {
            $payload = json_decode($row->payload ?? '{}', true);
            if (($payload['status'] ?? '') === 'seated') {
                // Move this row to Lieutenant Governor
                DB::table('election_candidate_records')
                    ->where('id', $row->id)
                    ->update(['political_office' => 'Lieutenant Governor']);
            }
        }

        // Ensure any Politician row mistakenly set to Governor+seated is corrected to Lt. Gov
        DB::table('politicians')
            ->whereRaw("LOWER(full_name) = 'eleni kounalakis'")
            ->where('state', 'CA')
            ->whereRaw("LOWER(COALESCE(political_office,'')) = 'governor'")
            ->where('term_status', 'seated')
            ->update(['political_office' => 'Lieutenant Governor']);

        // Ensure a seated ECR row exists for her current Lt. Gov office.
        // If the enrich command already created one, skip. Otherwise insert.
        $exists = DB::table('election_candidate_records')
            ->whereRaw("LOWER(full_name) = 'eleni kounalakis'")
            ->where('state', 'CA')
            ->whereRaw("LOWER(COALESCE(political_office,'')) = 'lieutenant governor'")
            ->exists();

        if (! $exists) {
            DB::table('election_candidate_records')->insert([
                'full_name'          => 'Eleni Kounalakis',
                'political_office'   => 'Lieutenant Governor',
                'governance_level'   => 'state',
                'state'              => 'CA',
                'district'           => 'Statewide',
                'party_affiliation'  => 'Democratic',
                'source'             => 'manual_correction',
                'external_candidate_id' => 'ca-ltgov-eleni-kounalakis',
                'payload'            => json_encode([
                    'status'     => 'seated',
                    'term_end'   => 'January 2027',
                    'term_note'  => 'Term-limited · running for Governor 2026',
                ]),
                'last_seen_at'       => now(),
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        } else {
            // Ensure the existing row is marked seated
            DB::table('election_candidate_records')
                ->whereRaw("LOWER(full_name) = 'eleni kounalakis'")
                ->where('state', 'CA')
                ->whereRaw("LOWER(COALESCE(political_office,'')) = 'lieutenant governor'")
                ->update([
                    'payload'    => json_encode([
                        'status'     => 'seated',
                        'term_end'   => 'January 2027',
                        'term_note'  => 'Term-limited · running for Governor 2026',
                    ]),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        DB::table('election_candidate_records')
            ->where('source', 'manual_correction')
            ->whereRaw("LOWER(full_name) = 'eleni kounalakis'")
            ->where('state', 'CA')
            ->delete();
    }
};
