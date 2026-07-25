# Development

## Common Commands

| Task | Command |
|------|---------|
| Start Laravel | `php artisan serve` |
| Watch frontend (Vite) | `npm run dev` |
| Start both together | `npm run dev:all` |
| Start Reverb WebSocket server | `php artisan reverb:start` |
| Build for production | `npm run build` |
| Run migrations | `php artisan migrate` |
| Check migration status | `php artisan migrate:status` |
| Fresh migration (dev only) | `php artisan migrate:fresh` |
| Run all tests | `php artisan test` |
| Run unit tests | `php artisan test --testsuite=Unit` |
| Run feature tests | `php artisan test --testsuite=Feature` |
| Run with coverage | `php artisan test --coverage` |
| Code style (Pint) | `./vendor/bin/pint` |
| Create admin account | `php artisan admin:create --email=admin@u9itus.com` |
| Reset admin password | `php artisan admin:reset-password --email=admin@u9itus.com` |
| Setup Reverb keys | `php artisan reverb:setup` |

## Typical Development Workflow

1. **Start the dev server** — `npm run dev:all` (runs Laravel on :8000 + Vite watch)
2. **Edit backend** — controllers, models, services, routes
3. **Edit frontend** — Blade views and `resources/js` / `resources/css`
4. **Run tests** — `php artisan test`
5. **Fix code style** — `./vendor/bin/pint`
6. **Deploy** — push to Railway

## Test Suite

The test suite covers **275 tests** and **776 assertions**.

| Suite | Tests | Coverage |
|-------|-------|----------|
| `Unit/Services/FraudPreventionServiceTest` | 14 | Score calculation, all fraud flags, signal persistence, flag/hold/release/trust score |
| `Unit/Services/CampaignBillingServiceTest` | 9 | `recordTransaction`, credit ledger, procurement commissions, `finalizePaymentIntent` |
| `Unit/Services/PoliticalViewServiceTest` | 11 | Full view lifecycle, idempotency, state-targeted campaigns, earnings summary |
| `Unit/Services/StripePaymentServiceTest` | 6 | No-key error path, `ensureCustomer` null-safe, `parseWebhook` fallback |
| `Unit/Services/IpReputationServiceTest` | 9 | CIDR datacenter detection, Tor prefix match, score cap, cache, ipinfo.io mock |
| `Unit/Services/DeviceFingerprintServiceTest` | 14 | `generate` stability, `compare`, `storeIfNew`, bot UA detection |
| `Unit/Standalone/PublicProfileControllerDigDeeperTest` | 9 | Dig Deeper summary, transparency gate, local-candidate context |
| `Feature/Campaign/AdminApprovalTest` | 10 | Admin access control, approve/reject/stop/reactivate workflow |
| `Feature/Campaign/CampaignCrudTest` | 20 | Campaign CRUD, validation, submit-for-review, analytics, billing |
| `Feature/Api/ViewSessionLifecycleTest` | 13 | View session assign → start → progress → complete, referral earnings |
| `Feature/Api/*` | 25 | Politician/Voter/Admin API, health endpoint |
| `Feature/Billing/*` | 7 | Credit purchase, Stripe webhook (success/failure/idempotency/sig-verify) |
| `Feature/Auth/*` | 19 | Registration, login, email verification, password reset |
| `Feature/Standalone/VoterWatchTest` | 20 | Token delivery, watch session lifecycle, survey, payout, voter dashboard |
| `Feature/Console/RunCaliforniaImportSyncCommandTest` | 2 | California import sync success/failure |

Run a specific test by name:

```bash
php artisan test --filter=TestName
```

Run the Phase 7 release hardening regression pack:

```bash
composer test:release-hardening
```

## Real-Time (Reverb) Development Setup

```bash
# 1. Generate Reverb credentials
php artisan reverb:setup

# 2. Install the Reverb server package (if not already installed)
composer require laravel/reverb

# 3. Start everything
php artisan reverb:start &   # WebSocket server on :8080
npm run dev:all               # Laravel :8000 + Vite watch
```

Required `.env` variables for Reverb:

```env
BROADCAST_DRIVER=reverb
REVERB_APP_ID=<random>
REVERB_APP_KEY=<random-32-char>
REVERB_APP_SECRET=<random-32-char>
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY=${REVERB_APP_KEY}
VITE_REVERB_HOST=${REVERB_HOST}
VITE_REVERB_PORT=${REVERB_PORT}
VITE_REVERB_SCHEME=${REVERB_SCHEME}
```

## Dispatching WebSocket Events (PHP)

```php
use App\Services\ReverbBroadcastService;

// Campaign approved notification → politician channel
$broadcast->campaignApproved($campaign);

// Payout processed notification → voter channel
$broadcast->payoutProcessed($voter, $amount, 'PayPal', $ref);

// Fraud flag raised → admin channel
$broadcast->fraudFlagRaised($voter, $score, $reason);
```

## Frontend WebSocket Listeners

```js
import { listenAsVoter, toast } from "./echo";

listenAsVoter(window.AUTH_USER_ID, {
    onAdReady(data) {
        toast(data.message, "info");
        document.getElementById("watch-btn").href = data.watch_url;
    },
    onSessionCompleted(data) {
        toast(data.message, "success");
        document.getElementById("balance").textContent =
            "$" + data.new_balance.toFixed(2);
    },
    onPayoutProcessed(data) {
        toast(data.message, "success");
    },
});
```

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Frontend changes not showing | Ensure `npm run dev` is running; clear browser cache; check `public/build/manifest.json` exists |
| Migration errors | Run `php artisan migrate:status`; use `php artisan migrate:fresh` for clean slate (dev only) |
| Test failures | Run `php artisan test --filter=TestName` to isolate; check `phpunit.xml` for test DB config |
| Reverb connection issues | Verify `.env` Reverb variables; ensure port 8080 is not blocked |
## Platform Operations Commands

All commands below are intended to be run on the production Railway service via `railway run php artisan <command>`.

### Payouts

| Command | Description |
|---------|-------------|
| `php artisan payouts:process-viewer` | Run the daily voter payout batch (Stripe/PayPal/CashApp) |
| `php artisan payouts:reconcile-paypal` | Poll PayPal for outstanding payout status updates |
| `php artisan billing:recover-stuck` | Find succeeded Stripe charges with no credit ledger entry and re-apply credits |
| `php artisan billing:recover-stuck --dry-run` | Preview affected transactions without applying credits |

### Stripe Connect / Authentic User Verifier

| Command | Description |
|---------|-------------|
| `php artisan stripe:audit-connect-accounts` | Find voters whose `stripe_account_id` is stale (created under a different platform key) and clear it so they can re-onboard |
| `php artisan stripe:audit-connect-accounts --dry-run` | Preview stale accounts without modifying any records |
| `php artisan stripe:audit-connect-accounts --force` | Clear all stale accounts without an interactive confirmation prompt (for Railway shell) |

### Candidate & Politician Data

| Command | Description |
|---------|-------------|
| `php artisan politicians:reconcile-missing-profiles` | Backfill unclaimed politician rows from unlinked election candidate records |
| `php artisan politicians:reconcile-missing-profiles --dry-run` | Preview backfill without writing |
| `php artisan politicians:validate-profile-photos` | Validate politician profile photos via Anthropic vision + URL heuristics |
| `php artisan politicians:validate-profile-photos --fix-invalid` | Quarantine invalid photos |
| `php artisan candidates:import-election-results` | Import election results |
| `php artisan candidates:import-election-candidates` | Import candidate data from configured sources |
| `php artisan candidates:import-ballotpedia` | Pull Ballotpedia candidate data |
| `php artisan candidates:import-california` | Import California unclaimed politicians |
| `php artisan candidates:import-united-states` | Import nationwide unclaimed politicians |
| `php artisan candidates:verify-news` | Set `verification_status` on candidate news articles |
| `php artisan candidates:refresh-news` | Scrape and verify candidate news articles |
| `php artisan politicians:enrich-donors` | Enrich politician donor/finance data from OpenSecrets |
| `php artisan politicians:audit-data-integrity` | Run data integrity checks on all politician records |
| `php artisan politicians:normalize-district-format` | Normalize district format strings across all politician rows |
| `php artisan politicians:reconcile-status` | Reconcile politician status against election records |
| `php artisan politicians:backfill-photos` | Backfill missing profile photos |

### Viral Moments & Issue Badges

Enrichment pipelines that score politicians' public discourse and label them with the issues they talk about. All support `--limit`, `--stale-hours`, `--politician=<id|slug>`, `--force`, and `--dry-run`; each is scheduled nightly (see `routes/console.php`).

| Command | Description |
|---------|-------------|
| `php artisan politicians:enrich-moments` | Fetch YouTube viral clips, score them, and feature the top one per politician (05:00) |
| `php artisan politicians:enrich-cspan-moments` | Playwright-scrape C-SPAN video clips and score them (list-only — C-SPAN exposes no view counts, so the YouTube clip stays featured) (05:30) |
| `php artisan politicians:enrich-issue-badges` | Roll up verified news + viral-moment titles + Vote Smart positions into per-topic scores and grant `inferred_discourse` issue badges (06:30) |
| `php artisan politicians:enrich-issue-badges --politician=<slug> --dry-run` | Preview computed topic signals and which would grant a badge, without writing |
| `php artisan marketing:draft-posts` | Auto-draft blog Posts from a politician's recent news/viral moments (PendingApproval — nothing auto-published; gated on `u9itus.marketing.drafting.enabled`) |

### Admin & Platform Health

| Command | Description |
|---------|-------------|
| `php artisan admin:create --email=admin@u9itus.com --name="Admin"` | Create a new admin user |
| `php artisan admin:reset-password --email=admin@u9itus.com` | Reset an admin password |
| `php artisan admin:data-health` | Run platform-wide data health checks and output a summary |
| `php artisan roles:ensure` | Ensure all required Spatie permission roles exist |
| `php artisan email:diagnostic` | Send a test email and verify mail configuration |
| `php artisan transactions:recover-stuck` | Recover stuck/orphaned campaign transactions |
| `php artisan users:prune-never-logged-in --dry-run` | Preview accounts that have never logged in (older than 30 days, no earnings, not admin) |
| `php artisan users:prune-never-logged-in --force` | Delete all such accounts and their voter/politician profiles |
| `php artisan users:prune-never-logged-in --days=60 --force` | Same, but with a 60-day grace period |
| `php artisan users:prune-never-logged-in --example-only --dry-run` | Preview all Faker seed accounts (`@example.com/net/org`) regardless of login state |
| `php artisan users:prune-never-logged-in --example-only --force` | Delete all Faker seed accounts (preserves any with real financial activity) |
| `php artisan users:prune-never-logged-in --example-only --include-seed-admins --dry-run` | Preview seed accounts including those with the admin role |
| `php artisan users:prune-never-logged-in --example-only --include-seed-admins --force` | Delete all seed accounts including fake admins (real admin@u9itus.com is never affected) |

### Notifications & Comms

| Command | Description |
|---------|-------------|
| `php artisan voters:send-authentic-verifier-reminders` | Send reminder emails to voters who haven't completed Authentic User Verifier |
| `php artisan voters:send-low-balance-alerts` | Alert voters with a balance below the payout threshold |
| `php artisan voters:send-weekly-digest` | Send weekly earnings digest to active voters |
| `php artisan receipts:resend-pending` | Resend any pending payment receipts |

### District & Map

| Command | Description |
|---------|-------------|
| `php artisan districts:sync-config` | Sync district configuration from external sources |
| `php artisan districts:normalize-format` | Normalize district format strings |
| `php artisan districts:sync-census-population` | Sync census population data for districts |
| `php artisan opensecrets:scrape-districts` | Scrape OpenSecrets district data |

---
## References

- [Laravel Docs](https://laravel.com/docs)
- [GitHub Issues](https://github.com/jhead12/u9itus.dev/issues)

---

← [Security and Fraud](Security-and-Fraud.md) | [Deployment →](Deployment.md)
