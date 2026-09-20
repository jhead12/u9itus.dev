<?php

use App\Models\CandidateRoster;
use App\Models\ElectionCandidateRecord;
use App\Support\MapCandidateHygiene;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function rosterEntry(string $name, string $state, string $office, ?string $district = null): void
{
    CandidateRoster::create([
        'source' => 'fec', 'source_id' => md5($name.$state.$office), 'full_name' => $name,
        'identity_key' => MapCandidateHygiene::identityKey($name), 'state' => $state, 'office' => $office,
        'district' => $district, 'election_year' => 2026, 'last_seen_at' => now(),
    ]);
}

it('FEC-verifies a Senate candidate the map is missing and flags one who withdrew', function () {
    Cache::flush();
    Http::fake(['en.wikipedia.org/w/api.php*' => Http::response(['parse' => ['wikitext' => "====Advanced to general====\n* [[Mike Rogers]], former representative\n* [[Abdul El-Sayed]], physician\n====Withdrawn====\n* [[Some Dropout]]\n"]])]);

    rosterEntry('Mike Rogers', 'MI', 'S');
    rosterEntry('Abdul El-Sayed', 'MI', 'S');

    ElectionCandidateRecord::factory()->create([
        'source' => 'candidate_discovery', 'full_name' => 'Some Dropout', 'governance_level' => 'Federal', 'political_office' => 'U.S. Senator',
        'state' => 'MI', 'election_date' => '2026-11-03',
        'payload' => ['primary_result' => 'running', 'state' => 'MI', 'election_date' => '2026-11-03', 'political_office' => 'U.S. Senator'],
    ]);

    $this->artisan('politicians:audit-federal-fields', ['--office' => 'senate', '--state' => 'MI'])
        ->expectsOutputToContain('missing, verified')
        ->expectsOutputToContain('Some Dropout (withdrawn per Wikipedia)')
        ->expectsOutputToContain('2 missing candidate(s) FEC-verified')
        ->assertSuccessful();
});

it('rejects an unknown office', function () {
    $this->artisan('politicians:audit-federal-fields', ['--office' => 'mayor'])->assertFailed();
});
