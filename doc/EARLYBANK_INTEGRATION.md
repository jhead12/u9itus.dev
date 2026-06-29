# U9itus ↔ Early-bank.com Integration — All Phases

Early-bank.com is a referral marketing storefront that sits in front of u9itus.com.
Members pay a one-time $20 fee and earn commissions for recruiting voters onto the
u9itus platform. The two applications communicate via a private REST API and
signed webhooks, both deployed as sibling services in the same Railway project.

---

## Architecture Overview

```
Member (browser)
    │
    ▼
earlybank.com  ──── voter.registered ────►  u9itus.com
(Laravel 12)   ◄─── voter.referred  ────    (Laravel 12)
               ◄─── voter.earned    ────
               │
               │  POST /api/v1/earlybank/register-referral
               │  GET  /api/v1/earlybank/voter/{uuid}/earnings
               │  GET  /api/v1/earlybank/member/{uuid}/stats
               ▼
         Stripe (payouts)
```

**Auth between services:** Shared bearer token (`EARLYBANK_API_TOKEN`).
**Webhook auth:** HMAC-SHA256 signature in `X-EarlyBank-Signature` header.
**Deployment:** Both services in the same Railway project; communication over Railway private network (no public internet hop).

---

## Phase 1 — U9itus API additions
**Status: COMPLETE. All 401 tests passing.**
**Repo:** u9itus.dev

### What was built

| File | Purpose |
|---|---|
| `app/Http/Middleware/EarlyBankApiAuth.php` | Authenticates earlybank service calls via Bearer token (`hash_equals` against `services.earlybank.api_token`) |
| `app/Http/Controllers/Api/EarlyBankController.php` | Three server-to-server endpoints — registerReferral, voterEarnings, memberStats |
| `app/Services/EarlyBankWebhookService.php` | Outbound HMAC-signed webhooks to earlybank; fire-and-forget; respects `EARLYBANK_ENABLED` flag |
| `database/migrations/2026_06_26_120000_add_earlybank_member_id_to_voters_table.php` | Adds `earlybank_member_id` (uuid, nullable, indexed) and `earlybank_linked_at` to voters |
| `app/Http/Requests/StoreVoterRequest.php` | Added `earlybank_member_id` optional uuid field |
| `app/Http/Controllers/Api/VoterController.php` | Captures `earlybank_member_id` at registration + fires `voter.registered` webhook |
| `config/services.php` | Added `earlybank` config block |
| `routes/api.php` | Registered `/api/v1/earlybank/*` route group (throttle 120/min) |
| `app/Models/Voter.php` | Added `earlybank_member_id` and `earlybank_linked_at` to fillable + casts |
| `app/Services/PoliticalViewService.php` | Injects `EarlyBankWebhookService`; fires webhook after every view session completes |
| `bootstrap/app.php` | Registered `earlybank.api` middleware alias |
| `.env.example` | Added EARLYBANK_API_TOKEN, EARLYBANK_WEBHOOK_URL, EARLYBANK_WEBHOOK_SECRET, EARLYBANK_ENABLED |

### API endpoints (all require `Authorization: Bearer <EARLYBANK_API_TOKEN>`)

```
POST /api/v1/earlybank/register-referral
Body: { voter_uuid: uuid, earlybank_member_id: uuid }
201 → { status: "linked", voter_uuid, earlybank_member_id, linked_at }
200 → { status: "already_linked", ... }
404 → { error: "voter_not_found" }
409 → { error: "already_linked_to_other_member" }
422 → { error: "validation_failed", errors: {...} }

GET /api/v1/earlybank/voter/{voter:uuid}/earnings
200 → { voter_uuid, earlybank_member_id, sessions_completed, total_voter_payout, wallet_balance, total_earned }
403 → { error: "voter_not_linked_to_earlybank" }

GET /api/v1/earlybank/member/{member_id}/stats
200 → { earlybank_member_id, referred_voters, sessions_completed, total_voter_payout }
422 → { error: "invalid_member_id" }
```

### Outbound webhooks (u9itus → earlybank)

**Signature format:**
```
X-EarlyBank-Timestamp: <unix_seconds>
X-EarlyBank-Signature: t=<unix_seconds>,v1=<hmac_sha256(timestamp + "." + raw_body, EARLYBANK_WEBHOOK_SECRET)>
```

**Events fired:**

`voter.registered` — fires on voter signup when `earlybank_member_id` present:
```json
{ "event": "voter.registered", "occurred_at": "<ISO8601>",
  "data": { "voter_uuid": "<uuid>", "earlybank_member_id": "<uuid>", "registered_at": "<ISO8601>" } }
```

`voter.referred` — fires on first completed view session for a linked voter:
```json
{ "event": "voter.referred", "occurred_at": "<ISO8601>",
  "data": { "voter_uuid": "<uuid>", "earlybank_member_id": "<uuid>", "linked_at": "<ISO8601>", "first_session_uuid": "<uuid>" } }
```

`voter.earned` — fires on every completed view session for a linked voter:
```json
{ "event": "voter.earned", "occurred_at": "<ISO8601>",
  "data": { "voter_uuid": "<uuid>", "earlybank_member_id": "<uuid>", "session_uuid": "<uuid>", "payout_amount": 0.25, "completed_at": "<ISO8601>" } }
```

### U9itus env vars needed (add to Railway service)
```
EARLYBANK_API_TOKEN=<openssl rand -hex 32>         # shared bearer token
EARLYBANK_WEBHOOK_URL=<earlybank private Railway URL>/webhooks/u9itus
EARLYBANK_WEBHOOK_SECRET=<openssl rand -hex 32>    # HMAC signing key
EARLYBANK_ENABLED=false                            # flip to true after Phase 3
EARLYBANK_REFERRAL_COOKIE_DAYS=30                  # optional, default 30
```

---

## Phase 1.1 — Referral attribution cookie
**Status: COMPLETE.**
**Repo:** u9itus.dev

### Problem it solves
Without a cookie, `earlybank_member_id` is only captured if `?ref=<uuid>` is
on the URL at the exact moment the voter submits the registration form. Attribution
breaks whenever a visitor clicks the referral link, browses, and returns later.

### What was built

| File | Change |
|---|---|
| `app/Http/Middleware/CaptureEarlyBankReferral.php` | New middleware — reads `?ref=<uuid>` from any URL, validates UUID format, writes a 30-day httpOnly `earlybank_ref` cookie |
| `bootstrap/app.php` | Appended `CaptureEarlyBankReferral` to the `web` middleware group (runs on every page load) |
| `app/Http/Requests/StoreVoterRequest.php` | Added `prepareForValidation()` — reads `earlybank_ref` cookie as fallback when `earlybank_member_id` field is absent; form field always wins over cookie |
| `app/Http/Controllers/Api/VoterController.php` | Clears the `earlybank_ref` cookie on the response after successful registration, preventing double-attribution on shared machines |

### Attribution rules
- **Last click wins** — every `?ref=<uuid>` visit overwrites the cookie with the latest referrer UUID
- **Cookie wins over nothing** — if the voter has the cookie but no query param at registration time, the cookie is used
- **Form field wins over cookie** — if `earlybank_member_id` is explicitly posted (e.g. via the JSON API), it takes priority
- **30-day window** — configurable via `EARLYBANK_REFERRAL_COOKIE_DAYS` env var

### Cookie properties
- `Name:` `earlybank_ref`
- `HttpOnly:` true (not readable by JavaScript — XSS-safe)
- `SameSite:` Lax (sent on top-level navigation from earlybank.com links)
- `Secure:` true in production, false in local dev
- `TTL:` 30 days (2,592,000 seconds)

### Test cases (add to u9itus test suite)
1. Visit any URL with `?ref=<valid-uuid>` → cookie is set
2. Visit with `?ref=<not-a-uuid>` → cookie is NOT set
3. Cookie set → visit `/register` without `?ref=` → voter created with `earlybank_member_id` from cookie
4. Cookie set + `?ref=<different-uuid>` on registration form → form value wins
5. Successful registration → response clears the `earlybank_ref` cookie

---

## Phase 2 — earlybank.com application
**Status: COMPLETE (tests pending verification — see Phase 3).**
**Repo:** github.com/jhead12/earlybank.com

### What was built

#### Database
| Migration | Table | Purpose |
|---|---|---|
| `2026_06_26_120000_extend_users_for_earlybank` | users | Adds member_uuid (auto-generated), stripe_customer_id, subscription_active, subscription_paid_at, u9itus_voter_uuid |
| `2026_06_26_120100_create_subscriptions_table` | subscriptions | Stripe PaymentIntent records for $20 fee |
| `2026_06_26_120200_create_referrals_table` | referrals | Links member_id → u9itus_voter_uuid; populated by voter.registered webhook |
| `2026_06_26_120300_create_commissions_table` | commissions | TYPE_FLAT ($10 join bonus) and TYPE_PERCENTAGE (10% per session); unique key prevents duplicates |
| `2026_06_26_120400_create_payouts_table` | payouts | Weekly batch Stripe payouts to members |
| `2026_06_26_120500_create_inbound_webhook_events_table` | inbound_webhook_events | Idempotency log for incoming u9itus webhooks |

#### Services
| Class | Purpose |
|---|---|
| `U9itusApiClient` | Wraps all outbound HTTP calls to u9itus `/api/v1/earlybank/*` with Bearer auth |
| `SubscriptionService` | Creates Stripe PaymentIntent for $20 subscription |
| `ReferralLinkService` | Generates referral URL (`u9itus.com/register?earlybank_member_id=<uuid>`) + QR code via BaconQrCode |
| `CommissionService` | Calculates unpaid commission totals per member |
| `PayoutService` | Creates Payout records and calls Stripe to transfer funds |

#### Controllers & Routes
| Route | Controller | Description |
|---|---|---|
| `GET /` | welcome view | Landing/marketing page |
| `GET/POST /register` | AuthController | Member registration |
| `GET/POST /login` | AuthController | Member login |
| `POST /logout` | AuthController | Logout |
| `GET /subscribe` | SubscriptionController | $20 subscription page |
| `POST /subscribe` | SubscriptionController | Create Stripe PaymentIntent |
| `GET /dashboard` | DashboardController | Earnings, referral link, QR code, recruit list |
| `POST /webhooks/u9itus` | U9itusWebhookController | Inbound signed webhooks from u9itus |
| `POST /webhooks/stripe` | SubscriptionController | Stripe payment confirmation |

#### Webhook receiver — events handled
| Event | Action |
|---|---|
| `voter.registered` | Creates Referral row; sets joined_at |
| `voter.referred` | Credits $10 flat Commission (TYPE_FLAT); sets Referral.first_session_at |
| `voter.earned` | Credits 10% Commission (TYPE_PERCENTAGE) based on payout_amount |

Idempotency keys: `voter.registered:<voter_uuid>`, `voter.referred:<first_session_uuid>`, `voter.earned:<session_uuid>`.
All events reject with 401 on bad/expired signature; return 200 silently on replay.

#### Tests
| Test file | Covers |
|---|---|
| `tests/Feature/U9itusApiClientTest.php` | All 3 API client methods with `Http::fake()`; asserts Bearer header and URL |
| `tests/Feature/Webhooks/U9itusWebhookTest.php` | All 3 events, invalid signature, expired timestamp, idempotency |

#### Artisan commands
- `php artisan earlybank:process-payouts` — aggregates unpaid commissions per subscribed member, creates Payout rows, calls Stripe

#### Deploy files
- `Dockerfile` — PHP 8.4 Alpine, Composer 2, COMPOSER_NO_AUDIT=1 (avoids network hang on Packagist advisory fetch)
- `railway.json` — builder DOCKERFILE, healthcheck `/up`
- `Procfile` — `web`, `queue`, `scheduler` processes

### earlybank.com env vars needed (set in Railway service)
```
APP_NAME="Early-bank"
APP_URL=https://earlybank.com
APP_KEY=<php artisan key:generate --show>
APP_ENV=production
DB_CONNECTION=pgsql
DB_HOST=<Railway Postgres internal host>
DB_DATABASE=earlybank
DB_USERNAME=<user>
DB_PASSWORD=<password>
U9ITUS_API_URL=http://<u9itus.RAILWAY_PRIVATE_URL>
U9ITUS_API_TOKEN=<same value as u9itus EARLYBANK_API_TOKEN>
U9ITUS_WEBHOOK_SECRET=<same value as u9itus EARLYBANK_WEBHOOK_SECRET>
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_DRIVER=database
LOG_CHANNEL=stderr
```

---

## Phase 3 — Railway deployment + end-to-end verification
**Status: NOT STARTED**

### Step 3.1 — Add earlybank as a Railway service

In the Railway dashboard, inside the existing u9itus project:
1. **New Service → GitHub Repo → `jhead12/earlybank.com`**
2. Set root directory: `/` (repo root)
3. Railway will detect the `Dockerfile` automatically
4. Name the service `earlybank`

### Step 3.2 — Provision a Postgres database for earlybank
1. **New Service → Database → PostgreSQL** inside the same Railway project
2. Name it `earlybank-db`
3. Copy the internal connection variables into the `earlybank` service env

### Step 3.3 — Generate and wire the shared secrets

Run locally:
```bash
openssl rand -hex 32   # generates EARLYBANK_API_TOKEN
openssl rand -hex 32   # generates EARLYBANK_WEBHOOK_SECRET
```

Set on the **u9itus** Railway service:
```
EARLYBANK_API_TOKEN=<generated>
EARLYBANK_WEBHOOK_SECRET=<generated>
EARLYBANK_WEBHOOK_URL=http://${{earlybank.RAILWAY_PRIVATE_URL}}/webhooks/u9itus
EARLYBANK_ENABLED=true
```

Set on the **earlybank** Railway service:
```
U9ITUS_API_TOKEN=<same value>
U9ITUS_WEBHOOK_SECRET=<same value>
U9ITUS_API_URL=http://${{u9itus.RAILWAY_PRIVATE_URL}}
```

> **Note:** `${{service.RAILWAY_PRIVATE_URL}}` is Railway's reference syntax for private network URLs — use it exactly in the Railway dashboard env var fields and Railway will resolve it at runtime.

### Step 3.4 — Run migrations on earlybank

From the Railway earlybank service shell (or via `railway run`):
```bash
php artisan migrate --force
```

### Step 3.5 — Flip EARLYBANK_ENABLED

On the u9itus Railway service:
```
EARLYBANK_ENABLED=true
```

This is the master switch. Until this is true, u9itus fires no webhooks to earlybank and the API group still authenticates normally.

### Step 3.6 — End-to-end smoke test

1. Register as a member on earlybank.com → pay $20 → confirm dashboard shows referral link
2. Open the referral link: `u9itus.com/register?earlybank_member_id=<member_uuid>`
3. Register a voter on u9itus using that link
4. Confirm in earlybank DB: `referrals` row created (voter.registered webhook received)
5. Simulate a view session completion on u9itus (Tinker: `PoliticalViewService::completeView(...)`)
6. Confirm in earlybank DB: `commissions` row with TYPE_FLAT $10 (voter.referred) + TYPE_PERCENTAGE 10% (voter.earned)
7. Run `php artisan earlybank:process-payouts` — confirm Payout row created + Stripe payout initiated
8. Confirm `inbound_webhook_events` table has all 3 events with `processed_at` set

### Step 3.7 — Legal geo-fencing (before public launch)

Per the EarlyBank Advertising plan and the legal risk analysis:
- Disable voter payouts (the u9itus side) in states where "anything of value" tied to political activity is restricted
- Add a state-level feature flag in `PlatformSettings` (u9itus already has this system)
- Get an election-law opinion letter before scaling beyond test users

### Step 3.8 — Scheduler (weekly payouts)

In the earlybank `routes/console.php`, schedule `ProcessPayouts` command:
```php
Schedule::command('earlybank:process-payouts')->weekly()->fridays()->at('09:00');
```

The `scheduler` Procfile process handles this — no extra Railway services needed.

---

## Shared secrets reference

| Secret | Set on | Value |
|---|---|---|
| `EARLYBANK_API_TOKEN` | u9itus Railway service | `openssl rand -hex 32` |
| `U9ITUS_API_TOKEN` | earlybank Railway service | **Same value** as EARLYBANK_API_TOKEN |
| `EARLYBANK_WEBHOOK_SECRET` | u9itus Railway service | `openssl rand -hex 32` |
| `U9ITUS_WEBHOOK_SECRET` | earlybank Railway service | **Same value** as EARLYBANK_WEBHOOK_SECRET |

## Commission model reference

| Trigger | Event | Commission type | Amount |
|---|---|---|---|
| Voter signs up via referral link | `voter.registered` | — (no commission yet; Referral row created) | $0 |
| Voter completes first view session | `voter.referred` | TYPE_FLAT | $10.00 |
| Voter completes any view session | `voter.earned` | TYPE_PERCENTAGE | 10% × payout_amount (≈ $0.025 per session at $0.25/view) |
| Member recruits politician/merchant who re-purchases | future | TYPE_REPEAT_PURCHASE | TBD |

Weekly payout to member = sum of all unpaid TYPE_FLAT + TYPE_PERCENTAGE commissions.
