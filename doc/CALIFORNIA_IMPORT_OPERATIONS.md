# California Import Operations Runbook

## Purpose

Operate and monitor the recurring California profile import workflow.

## Scheduled Jobs

- Daily import sync: `imports:sync-california` at `02:00` America/Los_Angeles.
- Hourly freshness check: `imports:check-california-health`.

## Admin Monitoring

- Admin dashboard: `/admin/imports`
- Displays:
  - latest run status
  - created/updated/skipped/campaign counts
  - exit code and error details
  - paginated historical run logs
- In-app admin notifications (bell):
  - `success`
  - `failure`
  - `stale`

## Required Runtime Processes

Ensure these processes are running in production:

- scheduler worker: `php artisan schedule:work --no-interaction`
- queue worker: `php artisan queue:work --queue=default --sleep=3 --tries=3 --timeout=600 --no-interaction`

## Manual Verification Commands

### Local simulation

```bash
php artisan migrate --force
php artisan imports:sync-california --dry-run
php artisan imports:sync-california
php artisan imports:check-california-health --max-age-hours=30
```

Expected outcomes:

- sync command reports success
- health check reports healthy after a recent non-dry-run success

### Production one-off checks

Run from Railway shell/session:

```bash
php artisan imports:sync-california
php artisan imports:check-california-health --max-age-hours=30
```

## Incident Handling

### Failure notification received

1. Open `/admin/imports` and inspect latest failed run.
2. Review `error_message` and `exit_code`.
3. Retry manually: `php artisan imports:sync-california`.
4. Confirm successful retry appears in `/admin/imports`.

### Stale notification received

1. Confirm last successful run age in `/admin/imports`.
2. Verify scheduler process is running.
3. Trigger manual import: `php artisan imports:sync-california`.
4. Re-run health check: `php artisan imports:check-california-health --max-age-hours=30`.

## Logging Sources

- App logs: `storage/logs/laravel.log`
- Scheduler logs (if configured): `storage/logs/scheduler.log`
- Queue logs (if configured): `storage/logs/queue-worker.log`

## Change Notes

- Import monitoring route: `/admin/imports`
- Notification class: `App\\Notifications\\CaliforniaImportNotification`
- Wrapper command: `imports:sync-california`
- Health check command: `imports:check-california-health`
