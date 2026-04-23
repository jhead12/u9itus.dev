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

