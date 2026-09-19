<?php

use App\Models\Politician;
use App\Services\OpenSecretsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    OpenSecretsService::resetTelemetry();
});

/**
 * An OpenSecretsService whose "Node scraper" prints the next canned JSON blob
 * (or `blocked` once the queue is empty) — no browser involved.
 */
function scraperReplying(array $outputs = []): OpenSecretsService
{
    return new class($outputs) extends OpenSecretsService {
        public int $spawned = 0;

        public function __construct(public array $queue) {}

        protected function newProcess(array $cmd): Process
        {
            $this->spawned++;
            $json = array_shift($this->queue) ?? ['error' => 'blocked'];

            return new Process(['php', '-r', 'echo '.var_export(json_encode($json), true).';']);
        }
    };
}

function scrapeFor(OpenSecretsService $service, int $count): array
{
    return collect(range(1, $count))
        ->map(fn () => $service->fetchCampaignFinanceData(Politician::factory()->create(['state' => 'CA'])))
        ->all();
}

const OS_FOUND = ['top_contributors' => [['name' => 'Acme PAC']], 'top_industries' => [], 'summary' => [], 'profile_url' => 'https://www.opensecrets.org/x', 'mpid' => '1'];

it('stops spawning the scraper after 10 consecutive bot-check blocks', function () {
    $service = scraperReplying();

    scrapeFor($service, 14);

    expect($service->spawned)->toBe(10)
        ->and(OpenSecretsService::wasShortCircuited())->toBeTrue()
        ->and(OpenSecretsService::getBlockedCount())->toBe(10)
        ->and(OpenSecretsService::getSkippedCount())->toBe(4);
});

it('does not trip while blocks are interleaved with real answers', function () {
    // 9 blocks, one success, 9 blocks, one "not found": never 10 in a row.
    $service = scraperReplying([
        ...array_fill(0, 9, ['error' => 'blocked']), OS_FOUND,
        ...array_fill(0, 9, ['error' => 'blocked']), ['error' => 'not_found'],
        ['error' => 'blocked'],
    ]);

    scrapeFor($service, 21);

    expect($service->spawned)->toBe(21)
        ->and(OpenSecretsService::wasShortCircuited())->toBeFalse()
        ->and(OpenSecretsService::getSkippedCount())->toBe(0);
});

it('still returns data for a successful scrape and reports blocked ones as no data', function () {
    $service = scraperReplying([OS_FOUND, ['error' => 'blocked']]);

    [$found, $blocked] = scrapeFor($service, 2);

    expect($found['top_contributors'])->toBe([['name' => 'Acme PAC']])
        ->and($blocked)->toBeNull();
});

it('clears the block count between runs', function () {
    scrapeFor(scraperReplying(), 10);
    expect(OpenSecretsService::wasShortCircuited())->toBeTrue();

    OpenSecretsService::resetTelemetry();

    expect(OpenSecretsService::wasShortCircuited())->toBeFalse()
        ->and(OpenSecretsService::getBlockedCount())->toBe(0);
});
