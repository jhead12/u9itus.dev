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

// Saved-places digest (map favorites) — runs daily but each voter is only
// actually emailed on their own content-driven cadence (floor: 7 days since
// their last send; burst: 2+ days plus enough new content — e.g. an election
// approaching for a saved district). See SendBoundaryDigest for the rule.
// Offset from the digest above to avoid dispatch contention.
Schedule::command('notifications:boundary-digest')->dailyAt('08:30');

// Prune unconfirmed guest saved-places digest opt-ins older than 14 days.
Schedule::command('guests:prune-unconfirmed-digest-signups')->daily();

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

// Endorsement backfill/re-scan — live ingestion (CandidateNewsService::
// persistArticles) already classifies each article once at fetch time, so
// this only needs to catch stragglers and pick up config/endorsements.php
// pattern updates on already-stored articles. Daily is plenty.
Schedule::command('candidates:detect-endorsements --limit=300')
    ->daily()
    ->withoutOverlapping();

// Donor/sponsor enrichment — refresh cached OpenSecrets + FEC data nightly.
// The GitHub Actions workflow (enrich-donor-snapshots.yml) also fires this.
Schedule::command('politicians:enrich-donors --stale-hours=48 --limit=200')
    ->dailyAt('03:00')
    ->withoutOverlapping();

// Public-directory profile enrichment — fetch each politician's official /
// campaign website for contact methods, office addresses (residential
// rejected), social/newsletter links, and donation page URLs (link-out only).
// Slotted at 04:00 to follow the 03:00 donor-enrich job.
Schedule::command('politicians:enrich-profiles --stale-hours=48 --limit=200')
    ->dailyAt('04:00')
    ->withoutOverlapping();

// Viral-moment enrichment — fetch YouTube clip view counts + score them for the
// profile "Recent media moment" section and the map pin. Gated on news freshness
// to stay within YouTube quota. The GitHub Actions workflow
// (refresh-viral-moments.yml, every 6h) is the primary runner; this in-app
// schedule is a backstop. Slotted at 05:00 to follow the 04:00 profile-enrich job.
Schedule::command('politicians:enrich-moments --stale-hours=48 --limit=200')
    ->dailyAt('05:00')
    ->withoutOverlapping();

// C-SPAN viral-moment enrichment — drives the Playwright scrape (scripts/
// scrape-cspan.js) through the same pipeline as the YouTube pass. Run on its
// own slot so the slow browser scrape doesn't block the quota-bounded YouTube
// run. C-SPAN clips carry no view counts → they surface in the list by recency,
// not as the featured map-pin clip. Slotted at 05:30 to follow the 05:00
// YouTube-moments job. Gated on CSPAN_MOMENTS_ENABLED + Node/Playwright runtime.
Schedule::command('politicians:enrich-cspan-moments --stale-hours=48 --limit=200')
    ->dailyAt('05:30')
    ->withoutOverlapping();

// Marketing content agent — auto-draft blog Posts from recent news / viral
// moments for politicians. Drafts are saved as PendingApproval and require a
// human to publish. Slotted at 06:00 to follow the 05:00 viral-moments enrich
// job so freshly-scored moments + verified news are available for selection.
// Gated on config('u9itus.marketing.drafting.enabled') (default false).
Schedule::command('marketing:draft-posts --limit=20')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground();

// Daily admin digest of the marketing content agent's new PendingApproval
// drafts (created since the last digest). Slotted at 07:00, after the 06:00
// draft job, so the day's freshly drafted posts are included. The command is
// itself gated on config('u9itus.marketing.drafting.digest_enabled').
Schedule::command('marketing:drafts-digest')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->runInBackground();

// Inferred issue/discourse badges — roll up each politician's verified news
// (stored topic_key) + viral-moment clip titles + Vote Smart NPAT positions
// into politician_topic_signals, then grant an `inferred_discourse` badge for
// any topic crossing the configured score threshold. Slotted at 06:30 to
// follow the 05:00 YouTube + 05:30 C-SPAN moment jobs and the 06:00 marketing
// draft pass, so all upstream evidence is fresh. Gated on
// config('u9itus.issues.enabled') (default true).
Schedule::command('politicians:enrich-issue-badges --stale-hours=48 --limit=200')
    ->dailyAt('06:30')
    ->withoutOverlapping()
    ->runInBackground();

// Census city demographics — refresh poverty / education / income + precomputed
// congressional districts for the curated ~200-city allow-list (all 50 states
// + DC). ACS data updates yearly, so weekly is plenty. The GitHub Actions
// workflow (sync-census-demographics.yml, Sunday 05:00 UTC) is the primary
// runner; this in-app schedule is a backstop so it still runs when web traffic
// is driving the scheduler even if the GA cron silently stops.
Schedule::command('geo:sync-census-demographics')
    ->weeklyOn(0, '07:00')
    ->withoutOverlapping();

// State-level poverty rate — powers the map's "Poverty Rate" choropleth
// layer (all 50 states + DC in one ACS call, unlike the city-level sync
// above). Same weekly cadence — ACS estimates update yearly.
Schedule::command('geo:sync-state-poverty-rate')
    ->weeklyOn(0, '07:15')
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
// sync-census-demographics.yml, the full sync-candidates.yml pipeline, and
// refresh-candidate-news.yml via GitHub workflow_dispatch instead of
// waiting for the next scheduled full sync.
Schedule::command('map:sync-hot-states')
    ->everyThreeHours()
    ->withoutOverlapping();

// Phase 20 — Event reminder emails (24-hour and 1-hour before start).
Schedule::command('events:send-reminders')
    ->hourly()
    ->withoutOverlapping();

// Deleted-account retention purge — permanently erases archived
// deleted_accounts snapshots (the PII UserDeletionService::archiveAndDelete
// captured) once they're past config('u9itus.deleted_account_retention_days')
// (default 90 days). After purge, those accounts can no longer be restored.
Schedule::command('deleted-accounts:purge')
    ->dailyAt('01:00')
    ->withoutOverlapping();

// Guest Trial Mode cleanup — deletes guest-provisioned voter accounts
// (ProvisionGuestVoterSession) whose trial expired more than 14 days ago.
Schedule::command('guests:prune-expired')
    ->dailyAt('04:00')
    ->withoutOverlapping();

