<?php

use App\Models\ElectionDataSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config(['services.google.civic_api_key' => 'DEMO_KEY']);
});

function civicResolveRow(array $overrides = []): ElectionDataSource
{
    return ElectionDataSource::create(array_merge([
        'ocd_id' => 'ocd-division/country:us/state:de/county:new_castle',
        'level' => 'county',
        'state' => 'DE',
        'jurisdiction_name' => 'New Castle County',
        'source_of_record' => 'census',
    ], $overrides));
}

function civicResolveFake(array $voterInfo, int $voterInfoStatus = 200): void
{
    Http::fake([
        'civicinfo.googleapis.com/civicinfo/v2/elections*' => Http::response([
            'elections' => [
                ['id' => '9468', 'name' => 'Delaware Primary Election', 'electionDay' => '2026-09-15', 'ocdDivisionId' => 'ocd-division/country:us/state:de'],
            ],
        ], 200),
        'civicinfo.googleapis.com/civicinfo/v2/voterinfo*' => Http::response($voterInfo, $voterInfoStatus),
    ]);
}

it('fails without an API key', function () {
    config(['services.google.civic_api_key' => null]);

    $this->artisan('civic:resolve-official-urls')->assertExitCode(1);
});

it('writes the local_jurisdiction authority + URLs onto a county row and infers vendor', function () {
    civicResolveRow();

    civicResolveFake([
        'election' => ['id' => '9468', 'name' => 'Delaware Primary Election', 'electionDay' => '2026-09-15'],
        'state' => [[
            'name' => 'Delaware',
            'electionAdministrationBody' => ['name' => 'Delaware Dept of Elections', 'electionInfoUrl' => 'https://elections.delaware.gov'],
            'local_jurisdiction' => [
                'name' => 'New Castle County',
                'id' => 'ocd-division/country:us/state:de/county:new_castle',
                'electionAdministrationBody' => [
                    'name' => 'New Castle County Board of Elections',
                    'electionInfoUrl' => 'https://vote.newcastlede.gov',
                    'ballotInfoUrl' => 'https://voteinfo.net/de-new-castle',
                ],
            ],
        ]],
    ]);

    $this->artisan('civic:resolve-official-urls', ['--state' => 'DE', '--stale-days' => 0])->assertExitCode(0);

    $row = ElectionDataSource::firstWhere('ocd_id', 'ocd-division/country:us/state:de/county:new_castle');
    expect($row->authority_name)->toBe('New Castle County Board of Elections')
        ->and($row->elections_home_url)->toBe('https://vote.newcastlede.gov')
        ->and($row->sample_ballot_url)->toBe('https://voteinfo.net/de-new-castle')
        ->and($row->vendor)->toBe('voteinfo_net')
        ->and($row->source_of_record)->toBe('google_civic')
        ->and($row->last_verified_at)->not->toBeNull();
});

it('falls back to config for a state row when voterInfoQuery returns 400', function () {
    civicResolveRow([
        'ocd_id' => 'ocd-division/country:us/state:ca',
        'level' => 'state',
        'state' => 'CA',
        'jurisdiction_name' => 'California',
    ]);

    civicResolveFake(['error' => ['message' => 'Election unknown']], 400);

    $this->artisan('civic:resolve-official-urls', ['--state' => 'CA', '--level' => 'state', '--stale-days' => 0])
        ->expectsOutputToContain('Config fallback: 1')
        ->assertExitCode(0);

    expect(ElectionDataSource::firstWhere('state', 'CA')->elections_home_url)
        ->toBe(config('civic.state_election_sites.CA'));
});

it('does not overwrite a human-set URL without --refresh', function () {
    civicResolveRow(['elections_home_url' => 'https://hand-curated.example', 'source_of_record' => 'manual']);

    civicResolveFake([
        'state' => [[
            'name' => 'Delaware',
            'local_jurisdiction' => [
                'name' => 'New Castle County',
                'electionAdministrationBody' => ['name' => 'NCC Elections', 'electionInfoUrl' => 'https://from-civic.example'],
            ],
        ]],
    ]);

    $this->artisan('civic:resolve-official-urls', ['--state' => 'DE', '--stale-days' => 0])->assertExitCode(0);

    expect(ElectionDataSource::first()->elections_home_url)->toBe('https://hand-curated.example');
});

it('--dry-run writes nothing', function () {
    civicResolveRow();

    civicResolveFake([
        'state' => [[
            'local_jurisdiction' => ['name' => 'NCC', 'electionAdministrationBody' => ['name' => 'NCC Elections', 'electionInfoUrl' => 'https://x.example']],
        ]],
    ]);

    $this->artisan('civic:resolve-official-urls', ['--state' => 'DE', '--stale-days' => 0, '--dry-run' => true])->assertExitCode(0);

    expect(ElectionDataSource::first()->authority_name)->toBeNull();
});
