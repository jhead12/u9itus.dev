<?php

use App\Models\Committee;
use App\Models\CommitteeProfile;
use App\Services\FECService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('batch enrichment runs without crashing and saves a missing committee profile', function () {
    $committee = Committee::create([
        'fec_committee_id' => 'C00000001',
        'name' => 'Example Committee',
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);

    $fec = Mockery::mock(FECService::class);
    $fec->shouldReceive('isConfigured')->once()->andReturn(true);
    $fec->shouldReceive('getCommitteeDetail')->once()->with('C00000001')->andReturn([
        'name' => 'Example Committee',
    ]);
    $fec->shouldReceive('getCommitteeTotals')->once()->with('C00000001', Mockery::type('int'))->andReturn([]);
    $fec->shouldReceive('getCommitteeIndependentExpenditures')->once()->with('C00000001', Mockery::type('int'))->andReturn([]);
    $fec->shouldReceive('getCommitteeContributions')->once()->with('C00000001')->andReturn([]);
    app()->instance(FECService::class, $fec);

    Artisan::call('committees:enrich-profiles', ['--limit' => 1]);

    expect(Artisan::output())->toContain('Done. Enriched: 1 | Failed: 0')
        ->and(CommitteeProfile::where('committee_id', $committee->id)->exists())->toBeTrue();
});
