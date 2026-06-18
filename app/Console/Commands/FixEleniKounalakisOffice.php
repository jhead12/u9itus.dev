<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot data fix: move Eleni Kounalakis from Governor (seated) to
 * Lieutenant Governor (seated) in both election_candidate_records and politicians.
 *
 * Usage (from Railway shell):
 *   php artisan fix:eleni-office
 *   php artisan fix:eleni-office --dry-run
 */
class FixEleniKounalakisOffice extends Command
{
    protected $signature = 'fix:eleni-office {--dry-run : Show what would change without writing}';
    protected $description = 'Move Eleni Kounalakis from Governor→seated to Lieutenant Governor→seated in DB.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $this->line($dry ? '[DRY RUN — no writes]' : '[LIVE — writing to DB]');

        // ── 1. Dump every row for Eleni so we can see what is there ──────────
        $this->line("\n=== election_candidate_records ===");
        $ecrs = DB::table('election_candidate_records')
            ->whereRaw("LOWER(full_name) LIKE '%kounalakis%' OR LOWER(full_name) LIKE '%eleni%'")
            ->get(['id', 'full_name', 'political_office', 'state', 'governance_level', 'source', 'payload']);

        foreach ($ecrs as $r) {
            $this->line("  #{$r->id} | {$r->full_name} | office={$r->political_office} | state={$r->state} | source={$r->source}");
            $this->line("       payload=" . substr((string) $r->payload, 0, 200));
        }

        $this->line("\n=== politicians ===");
        $pols = DB::table('politicians')
            ->whereRaw("LOWER(full_name) LIKE '%kounalakis%' OR LOWER(full_name) LIKE '%eleni%'")
            ->get(['id', 'full_name', 'political_office', 'state', 'term_status', 'is_running_candidate']);

        foreach ($pols as $p) {
            $this->line("  #{$p->id} | {$p->full_name} | office={$p->political_office} | state={$p->state} | term_status={$p->term_status}");
        }

        // ── 2. Fix ALL Governor rows for Eleni in CA → Lieutenant Governor ──
        // (regardless of payload — the map shows her as seated under Governor which is wrong)
        $this->line("\n=== Applying fixes ===");

        $ecrFixed = 0;
        foreach ($ecrs as $r) {
            if (
                strtolower((string) $r->state) === 'ca' &&
                strtolower(trim((string) $r->political_office)) === 'governor'
            ) {
                // Decode payload to check status
                $payload = json_decode((string) $r->payload, true) ?? [];
                $status  = $payload['status'] ?? '';

                if ($status === 'seated') {
                    $this->line("  ECR #{$r->id}: '{$r->full_name}' governor+seated → Lieutenant Governor");
                    if (! $dry) {
                        DB::table('election_candidate_records')
                            ->where('id', $r->id)
                            ->update([
                                'political_office' => 'Lieutenant Governor',
                                'updated_at'       => now(),
                            ]);
                    }
                    $ecrFixed++;
                } else {
                    $this->line("  ECR #{$r->id}: keeping as Governor candidate (status={$status})");
                }
            }
        }

        $polFixed = 0;
        foreach ($pols as $p) {
            if (
                strtolower((string) $p->state) === 'ca' &&
                strtolower(trim((string) $p->political_office)) === 'governor' &&
                in_array(strtolower((string) $p->term_status), ['seated', 'current', ''], true)
            ) {
                $this->line("  POL #{$p->id}: '{$p->full_name}' governor+seated → Lieutenant Governor");
                if (! $dry) {
                    DB::table('politicians')
                        ->where('id', $p->id)
                        ->update([
                            'political_office' => 'Lieutenant Governor',
                            'updated_at'       => now(),
                        ]);
                }
                $polFixed++;
            }
        }

        // ── 3. Ensure a seated Lieutenant Governor ECR row exists ────────────
        $ltGovExists = DB::table('election_candidate_records')
            ->whereRaw("LOWER(full_name) LIKE '%kounalakis%'")
            ->where('state', 'CA')
            ->whereRaw("LOWER(political_office) = 'lieutenant governor'")
            ->exists();

        if (! $ltGovExists) {
            $this->line("  Inserting seated Lieutenant Governor ECR row...");
            if (! $dry) {
                DB::table('election_candidate_records')->insert([
                    'full_name'             => 'Eleni Kounalakis',
                    'political_office'      => 'Lieutenant Governor',
                    'governance_level'      => 'state',
                    'state'                 => 'CA',
                    'district'              => 'Statewide',
                    'party_affiliation'     => 'Democratic',
                    'source'                => 'manual_correction',
                    'external_candidate_id' => 'ca-ltgov-eleni-kounalakis',
                    'payload'               => json_encode([
                        'status'   => 'seated',
                        'term_end' => 'January 2027',
                        'term_note'=> 'Term ends Jan 2027',
                    ]),
                    'last_seen_at' => now(),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        } else {
            $this->line("  Lieutenant Governor seated row already exists — updating payload...");
            if (! $dry) {
                DB::table('election_candidate_records')
                    ->whereRaw("LOWER(full_name) LIKE '%kounalakis%'")
                    ->where('state', 'CA')
                    ->whereRaw("LOWER(political_office) = 'lieutenant governor'")
                    ->update([
                        'payload'    => json_encode([
                            'status'   => 'seated',
                            'term_end' => 'January 2027',
                            'term_note'=> 'Term ends Jan 2027',
                        ]),
                        'updated_at' => now(),
                    ]);
            }
        }

        // ── 4. Fix Governor: Gavin Newsom should be seated (term-limited) ────
        $this->line("\n=== Fixing Governor rows ===");

        // Mark Newsom as seated
        $newsom = DB::table('election_candidate_records')
            ->whereRaw("LOWER(full_name) = 'gavin newsom'")
            ->where('state', 'CA')
            ->whereRaw("LOWER(political_office) = 'governor'")
            ->first();
        if ($newsom) {
            $this->line("  Marking Gavin Newsom as seated Governor...");
            if (! $dry) {
                DB::table('election_candidate_records')
                    ->where('id', $newsom->id)
                    ->update([
                        'payload'    => json_encode([
                            'status'   => 'seated',
                            'term_end' => 'January 2027',
                            'term_note'=> 'Term-limited · cannot seek re-election as Governor',
                        ]),
                        'updated_at' => now(),
                    ]);
            }
        }

        // Remove Chad Bianco from Governor general candidates — he was eliminated in primary
        $bianco = DB::table('election_candidate_records')
            ->whereRaw("LOWER(full_name) = 'chad bianco'")
            ->where('state', 'CA')
            ->whereRaw("LOWER(political_office) = 'governor'")
            ->first();
        if ($bianco) {
            $p = json_decode((string) $bianco->payload, true) ?? [];
            $this->line("  Chad Bianco Gov row — marking eliminated in primary...");
            if (! $dry) {
                DB::table('election_candidate_records')
                    ->where('id', $bianco->id)
                    ->update([
                        'payload'    => json_encode(array_merge($p, ['primary_result' => 'eliminated'])),
                        'updated_at' => now(),
                    ]);
            }
        }

        // Upsert Xavier Becerra — 2026 general candidate
        $becerra = DB::table('election_candidate_records')
            ->whereRaw("LOWER(full_name) = 'xavier becerra'")
            ->where('state', 'CA')
            ->whereRaw("LOWER(political_office) = 'governor'")
            ->first();
        if ($becerra) {
            $this->line("  Updating Xavier Becerra → advanced_to_general...");
            if (! $dry) {
                DB::table('election_candidate_records')
                    ->where('id', $becerra->id)
                    ->update([
                        'payload'    => json_encode(['primary_result' => 'advanced_to_general', 'general_date' => '2026-11-03']),
                        'updated_at' => now(),
                    ]);
            }
        } else {
            $this->line("  Inserting Xavier Becerra as Governor general candidate...");
            if (! $dry) {
                DB::table('election_candidate_records')->insert([
                    'full_name'             => 'Xavier Becerra',
                    'political_office'      => 'Governor',
                    'governance_level'      => 'state',
                    'state'                 => 'CA',
                    'district'              => 'Statewide',
                    'party_affiliation'     => 'Democratic',
                    'source'                => 'manual_correction',
                    'external_candidate_id' => 'ca-2026-gov-becerra',
                    'payload'               => json_encode(['primary_result' => 'advanced_to_general', 'general_date' => '2026-11-03']),
                    'last_seen_at' => now(),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        }

        // Upsert Steve Hilton — 2026 general candidate
        $hilton = DB::table('election_candidate_records')
            ->whereRaw("LOWER(full_name) = 'steve hilton'")
            ->where('state', 'CA')
            ->whereRaw("LOWER(political_office) = 'governor'")
            ->first();
        if ($hilton) {
            $this->line("  Updating Steve Hilton → advanced_to_general...");
            if (! $dry) {
                DB::table('election_candidate_records')
                    ->where('id', $hilton->id)
                    ->update([
                        'payload'    => json_encode(['primary_result' => 'advanced_to_general', 'general_date' => '2026-11-03']),
                        'updated_at' => now(),
                    ]);
            }
        } else {
            $this->line("  Inserting Steve Hilton as Governor general candidate...");
            if (! $dry) {
                DB::table('election_candidate_records')->insert([
                    'full_name'             => 'Steve Hilton',
                    'political_office'      => 'Governor',
                    'governance_level'      => 'state',
                    'state'                 => 'CA',
                    'district'              => 'Statewide',
                    'party_affiliation'     => 'Republican',
                    'source'                => 'manual_correction',
                    'external_candidate_id' => 'ca-2026-gov-hilton',
                    'payload'               => json_encode(['primary_result' => 'advanced_to_general', 'general_date' => '2026-11-03']),
                    'last_seen_at' => now(),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        }

        $this->newLine();
        $this->info("Done. ECR rows fixed: {$ecrFixed} | Politician rows fixed: {$polFixed}");
        return self::SUCCESS;
    }
}
