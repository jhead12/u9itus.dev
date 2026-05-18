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

## References

- [Laravel Docs](https://laravel.com/docs)
- [GitHub Issues](https://github.com/jhead12/u9itus.dev/issues)

---

← [Security and Fraud](Security-and-Fraud.md) | [Deployment →](Deployment.md)
