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

// Phase 7c — Notification digests
// Weekly earnings digest to voters who completed views (Mondays at 08:00)
Schedule::command('notifications:voter-digest')->weeklyOn(1, '08:00');

// Daily low-balance alerts to politicians whose credit balance is running low
Schedule::command('notifications:low-balance-alerts')->dailyAt('09:00');

