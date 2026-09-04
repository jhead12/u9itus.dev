<?php

use App\Jobs\BackfillStateElectionData;
use App\Models\BallotMeasure;
use App\Models\ElectionDataBackfill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Queue::fake();
});

it('queues a backfill and returns a check-back notice when a state has no measures', function () {
    $res = $this->getJson('/api/v1/mcp/ballot-measures?state=tx');

    $res->assertOk()
        ->assertJsonPath('count', 0)
        ->assertJsonPath('backfill.status', 'queued');

    Queue::assertPushed(BackfillStateElectionData::class, fn ($job) => $job->state === 'TX');
    expect(ElectionDataBackfill::firstWhere('state', 'TX')->status)->toBe('queued');
});

it('reports in_progress without re-queuing on a repeat search', function () {
    $this->getJson('/api/v1/mcp/ballot-measures?state=tx')->assertOk();
    $this->getJson('/api/v1/mcp/ballot-measures?state=tx')
        ->assertJsonPath('backfill.status', 'in_progress');

    Queue::assertPushed(BackfillStateElectionData::class, 1);
});

it('reports unavailable (and does not re-queue) when a recent run found nothing', function () {
    ElectionDataBackfill::create([
        'state' => 'TX',
        'status' => ElectionDataBackfill::STATUS_UNAVAILABLE,
        'last_attempted_at' => now(),
    ]);

    $this->getJson('/api/v1/mcp/ballot-measures?state=tx')
        ->assertOk()
        ->assertJsonPath('backfill.status', 'unavailable');

    Queue::assertNothingPushed();
});

it('omits the backfill block entirely when the state has measures', function () {
    BallotMeasure::create(['state' => 'CA', 'title' => 'Proposition 1', 'status' => 'upcoming']);

    $this->getJson('/api/v1/mcp/ballot-measures?state=ca')
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonMissingPath('backfill');
});

it('hands the agent a labelled read_more link when a measure has a source url', function () {
    BallotMeasure::create([
        'state' => 'CA',
        'title' => 'Proposition 37',
        'status' => 'upcoming',
        'source' => 'ballotpedia',
        'source_url' => 'https://ballotpedia.org/California_Proposition_37',
    ]);

    $this->getJson('/api/v1/mcp/ballot-measures?state=ca')
        ->assertOk()
        ->assertJsonPath('results.0.read_more.url', 'https://ballotpedia.org/California_Proposition_37')
        ->assertJsonPath('results.0.read_more.label', 'Full text & fiscal analysis on Ballotpedia');
});

it('leaves read_more null when a measure has no source url', function () {
    BallotMeasure::create(['state' => 'CA', 'title' => 'Proposition 1', 'status' => 'upcoming']);

    $this->getJson('/api/v1/mcp/ballot-measures?state=ca')
        ->assertOk()
        ->assertJsonPath('results.0.read_more', null);
});

it('watch endpoint registers an email and nudges the backfill', function () {
    $this->postJson('/api/v1/mcp/ballot-measures/watch', ['state' => 'tx', 'email' => 'me@example.com'])
        ->assertStatus(202)
        ->assertJsonPath('status', 'watching');

    $row = ElectionDataBackfill::firstWhere('state', 'TX');
    expect($row->watcherEmails())->toBe(['me@example.com']);
    Queue::assertPushed(BackfillStateElectionData::class);
});

it('watch endpoint short-circuits when the state already has measures', function () {
    BallotMeasure::create(['state' => 'CA', 'title' => 'Proposition 1', 'status' => 'upcoming']);

    $this->postJson('/api/v1/mcp/ballot-measures/watch', ['state' => 'ca', 'email' => 'me@example.com'])
        ->assertOk()
        ->assertJsonPath('status', 'already_available');

    expect(ElectionDataBackfill::count())->toBe(0);
    Queue::assertNothingPushed();
});
