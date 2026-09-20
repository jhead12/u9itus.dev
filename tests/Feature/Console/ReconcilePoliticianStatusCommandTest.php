<?php

use App\Models\Politician;
use Illuminate\Support\Facades\Http;

it('reconciles federal status without requesting the unused historical feed', function (bool $dryRun) {
    Http::preventStrayRequests();
    Http::fake([
        '*legislators-current.json' => Http::response([
            [
                'id' => ['bioguide' => 'C000001'],
                'name' => ['official_full' => 'Jane Carter'],
                'terms' => [['type' => 'rep', 'state' => 'CA', 'district' => 1, 'start' => '2025-01-03']],
            ],
        ]),
    ]);

    $seated = Politician::factory()->create([
        'full_name' => 'Jane Carter', 'state' => 'CA', 'user_id' => null,
        'governance_level' => 'Federal', 'term_status' => 'unknown',
        'is_running_candidate' => true,
    ]);
    $former = Politician::factory()->create([
        'full_name' => 'John Former', 'state' => 'CA', 'user_id' => null,
        'governance_level' => 'Federal', 'term_status' => 'seated',
        'is_running_candidate' => false,
    ]);

    $this->artisan('politicians:reconcile-status', [
        '--dry-run' => $dryRun,
        '--historical-url' => 'https://example.test/unused-history.json',
    ])->expectsOutputToContain('Feed loaded: 1 seated')->assertSuccessful();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/legislators-current.json'));
    expect($seated->refresh()->term_status)->toBe($dryRun ? 'unknown' : 'seated')
        ->and($seated->is_running_candidate)->toBeTrue()
        ->and($former->refresh()->term_status)->toBe($dryRun ? 'seated' : 'retired');
})->with([false, true]);
