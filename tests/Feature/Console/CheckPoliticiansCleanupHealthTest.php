<?php

use App\Models\PoliticianCleanupReview;
use App\Models\Politician;
use App\Models\User;
use App\Notifications\PoliticiansCleanupHealthNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function cleanupMetric(array $over = []): void
{
    DB::table('politician_cleanup_run_metrics')->insert(array_merge([
        'step' => 'flag-suspect-profiles', 'scope' => null, 'exit_code' => 0,
        'findings_count' => 0, 'auto_applied_count' => 0, 'queued_count' => 0,
        'breakdown' => json_encode([]),
        'started_at' => now(), 'finished_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ], $over));
}

it('alerts when a step has no recent run', function () {
    Notification::fake();
    User::factory()->create(['user_type' => 'admin']);
    cleanupMetric(['step' => 'prune-junk-ecrs']); // flag-suspect-profiles never ran

    $this->artisan('politicians:check-cleanup-health')->assertExitCode(0);

    Notification::assertSentTimes(PoliticiansCleanupHealthNotification::class, 1);
    Notification::assertSentTo(User::first(), function (PoliticiansCleanupHealthNotification $n) {
        return $n->eventType === 'missing_or_stale' && $n->details['step'] === 'flag-suspect-profiles';
    });
});

it('does not alert when every step ran recently and counts line up', function () {
    Notification::fake();
    User::factory()->create(['user_type' => 'admin']);
    cleanupMetric(['step' => 'flag-suspect-profiles', 'findings_count' => 2, 'queued_count' => 2]);
    cleanupMetric(['step' => 'prune-junk-ecrs', 'findings_count' => 3]);
    $pol = Politician::factory()->create(['slug' => 'health-check-'.fake()->unique()->numerify('####')]);
    PoliticianCleanupReview::create([
        'review_type' => PoliticianCleanupReview::TYPE_DEACTIVATE, 'politician_id' => $pol->id,
        'payload' => ['source' => 'flag-suspect-profiles'], 'status' => PoliticianCleanupReview::STATUS_PENDING,
    ]);
    PoliticianCleanupReview::create([
        'review_type' => PoliticianCleanupReview::TYPE_DEACTIVATE, 'politician_id' => $pol->id,
        'payload' => ['source' => 'flag-suspect-profiles'], 'status' => PoliticianCleanupReview::STATUS_PENDING,
    ]);

    $this->artisan('politicians:check-cleanup-health')->expectsOutputToContain('healthy')->assertExitCode(0);

    Notification::assertNothingSent();
});

it('alerts on a self-consistency mismatch between claimed and actual queued reviews', function () {
    Notification::fake();
    User::factory()->create(['user_type' => 'admin']);
    cleanupMetric(['step' => 'flag-suspect-profiles', 'findings_count' => 2, 'queued_count' => 2]);
    cleanupMetric(['step' => 'prune-junk-ecrs', 'findings_count' => 1]);
    // Claims 2 queued, but nothing was actually created in politician_cleanup_reviews today.

    $this->artisan('politicians:check-cleanup-health')->assertExitCode(0);

    Notification::assertSentTo(User::first(), function (PoliticiansCleanupHealthNotification $n) {
        return $n->eventType === 'self_consistency_mismatch';
    });
});

it('alerts when a step finds nothing against a materially non-zero baseline', function () {
    Notification::fake();
    User::factory()->create(['user_type' => 'admin']);
    // 14-day baseline averaging 5 findings/day...
    for ($i = 1; $i <= 5; $i++) {
        cleanupMetric(['step' => 'flag-suspect-profiles', 'findings_count' => 5, 'started_at' => now()->subDays($i)]);
    }
    // ...but today found nothing.
    cleanupMetric(['step' => 'flag-suspect-profiles', 'findings_count' => 0]);
    cleanupMetric(['step' => 'prune-junk-ecrs', 'findings_count' => 1]);

    $this->artisan('politicians:check-cleanup-health')->assertExitCode(0);

    Notification::assertSentTo(User::first(), function (PoliticiansCleanupHealthNotification $n) {
        return $n->eventType === 'finding_rate_zero';
    });
});
