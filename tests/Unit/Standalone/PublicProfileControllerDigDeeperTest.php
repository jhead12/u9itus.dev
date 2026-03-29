<?php

use App\Http\Controllers\Standalone\PublicProfileController;
use App\Models\Politician;
use App\Services\BallotpediaService;
use App\Services\FECService;
use App\Services\LocalCandidateAggregator;
use App\Services\OpenSecretsService;
use App\Services\VoteSmartService;
use Illuminate\Support\Collection;

function digDeeperController(): PublicProfileController
{
    return new class extends PublicProfileController {
        public function exposedBuildTransparencyData(Politician $politician): array
        {
            return $this->buildTransparencyData($politician);
        }

        public function exposedBuildDigDeeperData(Politician $politician, array $transparencyData): array
        {
            return $this->buildDigDeeperData($politician, $transparencyData);
        }

        public function exposedBuildDigDeeperSummary(array $details): string
        {
            return $this->buildDigDeeperSummary($details);
        }
    };
}

function verifiedPolitician(array $overrides = []): Politician
{
    return Politician::factory()->make(array_merge([
        'verification_status' => 'verified',
        'show_ballotpedia_data' => false,
        'show_opensecrets_data' => false,
        'show_votesmart_data' => false,
        'show_fec_data' => false,
        'state' => 'CA',
    ], $overrides));
}

test('dig deeper summary prefers first two scalar summary values', function () {
    $controller = digDeeperController();

    $summary = $controller->exposedBuildDigDeeperSummary([
        'summary' => [
            'total_raised' => '$1,000,000',
            'cash_on_hand' => '$250,000',
            'debt' => '',
        ],
    ]);

    expect($summary)->toBe('$1,000,000 • $250,000');
});

test('dig deeper summary falls back to section item counts', function () {
    $controller = digDeeperController();

    $summary = $controller->exposedBuildDigDeeperSummary([
        'sections' => [
            ['items' => [['a' => 1], ['b' => 2]]],
            ['items' => [['c' => 3]]],
        ],
    ]);

    expect($summary)->toBe('3 public records available');
});

test('dig deeper summary falls back to connected message when no summary or items exist', function () {
    $controller = digDeeperController();

    $summary = $controller->exposedBuildDigDeeperSummary([
        'summary' => [],
        'sections' => [],
    ]);

    expect($summary)->toBe('Source connected');
});

test('build transparency data returns empty when politician is not verified', function () {
    $controller = digDeeperController();

    $politician = verifiedPolitician([
        'verification_status' => 'pending',
        'show_ballotpedia_data' => true,
        'show_opensecrets_data' => true,
        'show_votesmart_data' => true,
        'show_fec_data' => true,
    ]);

    $result = $controller->exposedBuildTransparencyData($politician);

    expect($result)->toBe([]);
});

test('build transparency data includes only enabled sources that return display data', function () {
    $controller = digDeeperController();

    $politician = verifiedPolitician([
        'show_ballotpedia_data' => true,
        'show_opensecrets_data' => true,
    ]);

    $ballotpedia = Mockery::mock(BallotpediaService::class);
    $ballotpedia->shouldReceive('getDisplayData')
        ->once()
        ->andReturn([
            'source' => 'Ballotpedia',
            'source_url' => 'https://ballotpedia.org/example',
            'sections' => [],
        ]);

    $openSecrets = Mockery::mock(OpenSecretsService::class);
    $openSecrets->shouldReceive('getDisplayData')
        ->once()
        ->andReturn(null);

    app()->instance(BallotpediaService::class, $ballotpedia);
    app()->instance(OpenSecretsService::class, $openSecrets);

    $result = $controller->exposedBuildTransparencyData($politician);

    expect(array_keys($result))->toBe(['ballotpedia']);
});

test('build transparency data tolerates provider exceptions and returns remaining data', function () {
    $controller = digDeeperController();

    $politician = verifiedPolitician([
        'show_ballotpedia_data' => true,
        'show_opensecrets_data' => true,
    ]);

    $ballotpedia = Mockery::mock(BallotpediaService::class);
    $ballotpedia->shouldReceive('getDisplayData')
        ->once()
        ->andThrow(new RuntimeException('rate limited'));

    $openSecrets = Mockery::mock(OpenSecretsService::class);
    $openSecrets->shouldReceive('getDisplayData')
        ->once()
        ->andReturn([
            'source' => 'OpenSecrets',
            'source_url' => 'https://opensecrets.org/example',
            'sections' => [],
        ]);

    app()->instance(BallotpediaService::class, $ballotpedia);
    app()->instance(OpenSecretsService::class, $openSecrets);

    $result = $controller->exposedBuildTransparencyData($politician);

    expect(array_keys($result))->toBe(['opensecrets']);
});

test('build dig deeper data shows federal-only message for non-federal fec panel', function () {
    $controller = digDeeperController();

    $politician = verifiedPolitician([
        'political_office' => 'Mayor',
        'show_fec_data' => true,
    ]);

    $fec = Mockery::mock(FECService::class);
    $fec->shouldReceive('isFederalCandidate')
        ->once()
        ->with($politician)
        ->andReturn(false);

    $aggregator = Mockery::mock(LocalCandidateAggregator::class);
    $aggregator->shouldReceive('findByState')
        ->once()
        ->andReturn(collect());

    app()->instance(FECService::class, $fec);
    app()->instance(LocalCandidateAggregator::class, $aggregator);

    $result = $controller->exposedBuildDigDeeperData($politician, []);

    expect($result['enabled_sources_count'])->toBe(1)
        ->and($result['available_sources_count'])->toBe(0)
        ->and($result['panels'][0]['status'])->toBe('unavailable')
        ->and($result['panels'][0]['unavailable_reason'])->toBe('FEC reporting applies to federal offices only.');
});

test('build dig deeper data includes optional local candidate context when available', function () {
    $controller = digDeeperController();

    $politician = verifiedPolitician();

    $fec = Mockery::mock(FECService::class);
    $fec->shouldReceive('isFederalCandidate')
        ->once()
        ->andReturn(false);

    $aggregator = Mockery::mock(LocalCandidateAggregator::class);
    $aggregator->shouldReceive('findByState')
        ->once()
        ->andReturn(new Collection([
            ['source' => 'google_civic'],
            ['source' => 'google_civic'],
            ['source' => 'ballotpedia'],
        ]));

    app()->instance(FECService::class, $fec);
    app()->instance(LocalCandidateAggregator::class, $aggregator);

    $result = $controller->exposedBuildDigDeeperData($politician, []);

    expect($result['local_candidate_context'])->toBeArray()
        ->and($result['local_candidate_context']['state'])->toBe('CA')
        ->and($result['local_candidate_context']['candidate_count'])->toBe(3)
        ->and($result['local_candidate_context']['sources'])->toBe(['google_civic', 'ballotpedia']);
});

test('build dig deeper data ignores local candidate context failures', function () {
    $controller = digDeeperController();

    $politician = verifiedPolitician();

    $fec = Mockery::mock(FECService::class);
    $fec->shouldReceive('isFederalCandidate')
        ->once()
        ->andReturn(false);

    $aggregator = Mockery::mock(LocalCandidateAggregator::class);
    $aggregator->shouldReceive('findByState')
        ->once()
        ->andThrow(new RuntimeException('service unavailable'));

    app()->instance(FECService::class, $fec);
    app()->instance(LocalCandidateAggregator::class, $aggregator);

    $result = $controller->exposedBuildDigDeeperData($politician, []);

    expect($result['local_candidate_context'])->toBeNull();
});
