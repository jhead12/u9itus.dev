<?php

use App\Models\Politician;
use App\Models\PoliticianDonorSnapshot;
use App\Services\FECService;
use App\Services\OpenSecretsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function mockDonorEnrichmentServices(): void
{
    $openSecrets = Mockery::mock(OpenSecretsService::class);
    $openSecrets->shouldReceive('fetchCampaignFinanceData')->andReturn(null);
    app()->instance(OpenSecretsService::class, $openSecrets);

    $fec = Mockery::mock(FECService::class);
    $fec->shouldReceive('isConfigured')->andReturn(false);
    app()->instance(FECService::class, $fec);
}

test('--upcoming-only only targets currently-running candidates', function () {
    mockDonorEnrichmentServices();

    $running = Politician::factory()->create([
        'is_running_candidate' => true,
        'page_published' => true,
        'is_active' => true,
    ]);

    $notRunning = Politician::factory()->create([
        'is_running_candidate' => false,
        'page_published' => true,
        'is_active' => true,
    ]);

    Artisan::call('politicians:enrich-donors', ['--upcoming-only' => true]);

    expect(PoliticianDonorSnapshot::where('politician_id', $running->id)->exists())->toBeTrue();
    expect(PoliticianDonorSnapshot::where('politician_id', $notRunning->id)->exists())->toBeFalse();
});

test('without --upcoming-only both running and non-running candidates are targeted', function () {
    mockDonorEnrichmentServices();

    $running = Politician::factory()->create([
        'is_running_candidate' => true,
        'page_published' => true,
        'is_active' => true,
    ]);

    $notRunning = Politician::factory()->create([
        'is_running_candidate' => false,
        'page_published' => true,
        'is_active' => true,
    ]);

    Artisan::call('politicians:enrich-donors');

    expect(PoliticianDonorSnapshot::where('politician_id', $running->id)->exists())->toBeTrue();
    expect(PoliticianDonorSnapshot::where('politician_id', $notRunning->id)->exists())->toBeTrue();
});
