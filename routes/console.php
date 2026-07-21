<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule commands
Schedule::command('assignments:handle-expired')->hourly();
Schedule::command('payouts:process-viewer')->daily();
Schedule::command('payouts:reconcile-paypal')->hourly();
// Phase 14 — Campaign scheduling: activate/expire campaigns every 5 minutes
Schedule::command('campaigns:apply-schedule')->everyFiveMinutes();

// Phase 7c — Notification digests
// Weekly earnings digest to voters who completed views (Mondays at 08:00)
Schedule::command('notifications:voter-digest')->weeklyOn(1, '08:00');

// Daily low-balance alerts to politicians whose credit balance is running low
Schedule::command('notifications:low-balance-alerts')->dailyAt('09:00');

// Daily reminders for legacy voters to migrate to Authentic User Verifier.
Schedule::command('notifications:authentic-user-verifier-reminders')->dailyAt('10:00');

// Sprint 2 — Daily California candidate/profile sync with run logging + failure alerts
Schedule::command('imports:sync-california')
    ->timezone('America/Los_Angeles')
    ->dailyAt('02:00');

// Sprint 2 — Hourly California import health check (alerts if data is stale)
Schedule::command('imports:check-california-health')
    ->hourly();

// Candidate news feed — refresh stale candidate headlines every 6 hours.
// The GitHub Actions workflow (refresh-candidate-news.yml) also fires this
// on a cron so it runs even when no web traffic is hitting the scheduler.
Schedule::command('candidates:refresh-news --stale-hours=6 --limit=50')
    ->everySixHours()
    ->withoutOverlapping();

// Hourly health check for the candidate news refresh job — alerts admins if
// the GitHub Actions cron or this scheduler silently stops firing.
Schedule::command('news:check-refresh-health')
    ->hourly();

// Donor/sponsor enrichment — refresh cached OpenSecrets + FEC data nightly.
// The GitHub Actions workflow (enrich-donor-snapshots.yml) also fires this.
Schedule::command('politicians:enrich-donors --stale-hours=48 --limit=200')
    ->dailyAt('03:00')
    ->withoutOverlapping();

// Census city demographics — refresh poverty / education / income + precomputed
// congressional districts for the curated ~200-city allow-list (all 50 states
// + DC). ACS data updates yearly, so weekly is plenty. The GitHub Actions
// workflow (sync-census-demographics.yml, Sunday 05:00 UTC) is the primary
// runner; this in-app schedule is a backstop so it still runs when web traffic
// is driving the scheduler even if the GA cron silently stops.
Schedule::command('geo:sync-census-demographics')
    ->weeklyOn(0, '07:00')
    ->withoutOverlapping();

// Weekly politician lifecycle reconciliation — marks seated/retired/lost/running.
// Runs every Sunday at 04:00 UTC, after the candidate sync (02:00 UTC).
// After a general election, trigger manually with --election-date=YYYY-MM-DD.
Schedule::command('politicians:reconcile-status')
    ->weeklyOn(0, '04:00')
    ->withoutOverlapping();

// Traffic-responsive state sync — checks 3-D map click volume over a
// trailing window and, for whichever states are trending right now (e.g.
// a surge of clicks on NY versus CA), triggers refresh-map-candidates.yml,
// sync-census-demographics.yml, and the full sync-candidates.yml pipeline
// via GitHub workflow_dispatch instead of waiting for the next scheduled
// full sync.
Schedule::command('map:sync-hot-states')
    ->everyThreeHours()
    ->withoutOverlapping();

