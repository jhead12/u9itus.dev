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

test('committee ID placeholders are retried and replaced with FEC names', function () {
    config(['services.fec.api_key' => 'DEMO_KEY']);
    \Illuminate\Support\Facades\Cache::flush();
    \App\Models\Committee::create(['fec_committee_id' => 'C00785899', 'name' => 'C00785899']);
    \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response([
        'results' => [['committee_id' => 'C00785899', 'name' => 'Example Committee']],
    ])]);
    $service = new class extends FECService {
        public function resolveForTest(array $rows): array
        {
            $this->resolveCommitteeNames($rows);
            return $rows;
        }
        protected function sleepMicroseconds(int $microseconds): void {}
    };
    $rows = $service->resolveForTest([['committee_id' => 'C00785899', 'committee_name' => 'C00785899']]);
    expect($rows[0]['committee_name'])->toBe('Example Committee');
    expect(\App\Models\Committee::where('fec_committee_id', 'C00785899')->value('name'))->toBe('Example Committee');
});
