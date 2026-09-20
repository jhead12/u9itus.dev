<?php

use App\Models\CandidateRoster;
use App\Models\Politician;
use App\Models\User;
use App\Services\FECService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function fecConflictPolitician(array $extra = []): Politician
{
    return Politician::create(array_merge([
        'uuid' => Str::uuid(),
        'full_name' => 'David Ambrose',
        'state' => 'AK',
        'political_office' => 'U.S. Representative',
        'governance_level' => 'Federal',
        'is_active' => true,
        'slug' => 'david-ambrose-'.Str::random(5),
        'fec_candidate_id' => 'H2AK00556',
    ], $extra));
}

/** @param  array<int, array<string, mixed>>  $rows */
function fakeFecConflictList(array $rows): void
{
    $fec = Mockery::mock(FECService::class)->makePartial();
    $fec->shouldReceive('isConfigured')->andReturn(true);
    $fec->shouldReceive('listCandidates')->andReturn($rows);
    app()->instance(FECService::class, $fec);
}

function fecConflictAmbroseRow(): array
{
    return ['candidate_id' => 'H6AK01134', 'name' => 'AMBROSE, DAVID', 'candidate_status' => 'C', 'district' => '00'];
}

it('leaves a conflicting id alone by default', function () {
    $p = fecConflictPolitician();
    fakeFecConflictList([fecConflictAmbroseRow()]);

    $this->artisan('politicians:import-fec-candidates', ['--state' => 'AK', '--office' => 'H'])
        ->expectsOutputToContain('[CONFLICT]')
        ->assertSuccessful();

    expect($p->fresh()->fec_candidate_id)->toBe('H2AK00556');
});

it('conflicts-only lists both FEC links and writes nothing', function () {
    $p = fecConflictPolitician();
    fakeFecConflictList([fecConflictAmbroseRow()]);

    $this->artisan('politicians:import-fec-candidates', ['--state' => 'AK', '--office' => 'H', '--conflicts-only' => true])
        ->expectsOutputToContain('https://www.fec.gov/data/candidate/H2AK00556/')
        ->expectsOutputToContain('https://www.fec.gov/data/candidate/H6AK01134/')
        ->assertSuccessful();

    expect($p->fresh()->fec_candidate_id)->toBe('H2AK00556')
        ->and(CandidateRoster::count())->toBe(0);
});

it('overwrite-conflicts replaces a stale id', function () {
    $p = fecConflictPolitician();
    fakeFecConflictList([fecConflictAmbroseRow()]);

    $this->artisan('politicians:import-fec-candidates', ['--state' => 'AK', '--office' => 'H', '--overwrite-conflicts' => true])
        ->expectsOutputToContain('[REPLACED]')
        ->assertSuccessful();

    expect($p->fresh()->fec_candidate_id)->toBe('H6AK01134');
});

it('overwrite-conflicts keeps claimed profiles, other chambers, and ids that also filed this year', function () {
    $claimed = fecConflictPolitician(['user_id' => User::factory()->create()->id]);
    $senate = fecConflictPolitician(['fec_candidate_id' => 'S2AK00111']);
    $filedToo = fecConflictPolitician(['fec_candidate_id' => 'H8AK00999']);

    fakeFecConflictList([
        fecConflictAmbroseRow(),
        ['candidate_id' => 'H8AK00999', 'name' => 'SOMEONE, ELSE', 'candidate_status' => 'C', 'district' => '00'],
    ]);

    $this->artisan('politicians:import-fec-candidates', ['--state' => 'AK', '--office' => 'H', '--overwrite-conflicts' => true])
        ->expectsOutputToContain('profile is claimed')
        ->expectsOutputToContain('different chamber')
        ->expectsOutputToContain('a different person')
        ->assertSuccessful();

    expect($claimed->fresh()->fec_candidate_id)->toBe('H2AK00556')
        ->and($senate->fresh()->fec_candidate_id)->toBe('S2AK00111')
        ->and($filedToo->fresh()->fec_candidate_id)->toBe('H8AK00999');
});
