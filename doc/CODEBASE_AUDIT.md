# Codebase Audit & Refactor Tracker — u9itus.dev

**Scope:** Full `app/` layer of a Laravel 12 / PHP 8.4 political/voter-engagement platform
(~48.8k LOC, 58 controllers, 52 services, 45 models, 113 migrations, 88 test files).

**Audited:** 2026-07-15. Five parallel audits covering (1) controller architecture,
(2) models & database, (3) security, (4) services & duplication, (5) testing & code quality.

**How to use this doc:** Findings are grouped by severity. Each item has a checkbox —
tick it when the fix lands and note the commit/PR. The **Refactor Roadmap** at the bottom
is the ordered work plan (security first, then correctness bugs, then architecture).

> See also: `SECURITY.md` (existing), `refactor/admin-controller-split` branch (in progress — Step 7 complete, awaiting commit).

---

## Executive Summary — the 5 most urgent things

1. **Committed PayPal credentials** in `.env.example:133-134` are real (non-placeholder) sandbox
   secrets living in git history. Rotate now regardless of sandbox status.
2. ~~**KYC government-ID documents are stored on the public disk** (`PoliticianController.php:1614`)
   and are web-accessible at `/storage/kyc/{user_id}/document.{ext}` with **no auth** — every
   politician's ID is retrievable by enumerating sequential user IDs.~~ — **fixed** (`45cd633d`,
   2026-07-20, SEC-2). KYC now reads/writes the `local` disk via `Storage::disk('local')`; a one-time
   migration moves `storage/app/public/kyc` → `storage/app/private/kyc`. (Kept here for history.)
3. **The voter API is unauthenticated** (`routes/api.php:101-128`) — UUID is the only gate.
   It leaks PII (email, phone, wallet) and lets anyone mint a Stripe Connect onboarding link
   for any voter (`Api/VoterController.php:214`).
4. ~~**`ViewSession::isExpired()` is broken** (`ViewSession.php:134-138`)~~ — **fixed** (`32b840877`,
   2026-07-19). The enum-vs-string `in_array` compare that made the completed/flagged guard dead code
   is now a strict `in_array(..., true)` against the enum values. (Kept here for history.)
5. ~~**Batch payouts run live Stripe/PayPal/CashApp calls synchronously in the web request**
   (`AdminPayoutController.php:174` → `PoliticalPaymentService.php:135`) — will exceed the
   Railway web-worker timeout and leave half-dispatched runs.~~ — **fixed** (`ea63700d`,
   2026-07-20, ARCH-1). Web + API entry points now dispatch `ProcessBatchPayoutsJob`; the run has a
   `status` machine and per-voter counter increments. (Kept here for history.)

---

## Consolidated Findings

### 🔴 Critical / High Severity

#### Security

- [x] **SEC-1 — Committed PayPal credentials** — `.env.example:133-134`
  Real `PAYPAL_CLIENT_ID`/`PAYPAL_CLIENT_SECRET` checked into VCS (surrounding keys are placeholders).
  **Fix:** rotate in PayPal dashboard, replace with placeholders, purge from git history (BFG / `git filter-repo`), audit PayPal API logs for misuse.
  **Partial** (`37fa13e2`): `.env.example` values blanked to placeholders. Still outstanding (user/ops):
  purge the real secrets from git history (BFG / `git filter-repo` — they remain in pre-`37fa13e2`
  history), rotate the keys in the PayPal dashboard, and audit PayPal API logs for misuse.

- [x] **SEC-2 — KYC documents on the public disk** — `app/Http/Controllers/Standalone/PoliticianController.php:1614`, `config/filesystems.php:41-48`
  `uploadKycDocument()` stores to the `public` disk (web-served via `storage:link`); path uses the
  sequential auto-increment User ID, so `/storage/kyc/{id}/document.{ext}` is open to enumeration.
  **Fix:** store KYC on `local` disk (`storage_path('app/private')`) or private S3 prefix; serve only
  via the admin-gated `AdminKycController` with `response()->file` after authorization. Re-migrate
  existing files, purge `public/kyc`.
  **Done** (`45cd633d`): upload delete + `storeAs` + all three viewers (politician self-service, voter
  self-service, admin) now read/write the `local` disk via `Storage::disk('local')`. `kyc_document_path`
  stores the path relative to the disk root, so a one-time migration moves `storage/app/public/kyc` →
  `storage/app/private/kyc` (idempotent, merges if the destination exists) without a row rewrite. The
  admin view route is already admin-gated (`role:admin` + `admin.2fa` + `check.admin.onboarding`).

- [x] **SEC-3 — KYC upload trusts client-supplied extension** — `PoliticianController.php:1613-1614`
  Stored filename uses `getClientOriginalExtension()` while the `mimes:` rule checks content MIME.
  A JPEG/HTML polyglot uploaded as `document.html` passes the content check but is served as
  `text/html` → stored XSS against admins. Compounded by SEC-2.
  **Fix:** use `$file->guessExtension()` (derive from detected MIME), or hardcode extension from `mime_content_type`.
  **Done** (`0280d7ea`): switched to `guessExtension()` + allowlist-fallback to `bin`.

- [x] **SEC-4 — Voter API unauthenticated (IDOR)** — `routes/api.php:101-128`, `app/Http/Controllers/Api/VoterController.php`
  The `/api/v1/voters/{voter:uuid}` group sits outside `auth:sanctum` (throttle-only). `VoterResource`
  exposes email/phone/full_name/wallet_balance. `connectOnboard()` mints Stripe onboarding links and
  sets `payment_method='stripe'` for any supplied UUID.
  **Fix (as shipped):** the audit's proposed `auth:sanctum` + `VoterPolicy` (user_id owner-check) did not
  fit — Sanctum isn't installed and API-registered voters have `user_id=NULL` by design (the web flow
  later stitches an orphan voter to a User by email). Shipped per-voter bearer tokens instead: new
  `voter_api_tokens` table + `VoterApiToken` model (SHA-256-hashed), `voter-token`/`voter.owns` middleware,
  token issued at registration (`POST /api/v1/voters`) + `POST /api/v1/voters/token/rotate`; the token's
  voter must match `{voter:uuid}` / own `{session:uuid}`. Stripped `email`/`phone` from `VoterResource`.
  **BREAKING** — consumers must adopt tokens; registration + office-profile stay public. (`4401c558`)

- [x] **SEC-5 — Admin API bypasses 2FA** — `routes/api.php:157`
  `/api/v1/admin/*` uses `auth:sanctum` + `role:admin` but no `admin.2fa`/`EnsureAdminTwoFactorVerified`.
  State-changing ops (`approveCampaign`, `processBatchPayouts`, `blockIp`, `clearFraudFlag`) reachable
  with a Sanctum token alone.
  **Fix:** require 2FA for admin API tokens (issue tokens only after 2FA, or apply stateless 2FA challenge).
  **Done** (`8728abb8`): applied `admin.2fa` to the `/api/v1/admin` group (matching the web gate);
  `EnsureAdminTwoFactorVerified` now returns 403 JSON for `expectsJson()` callers.

- [x] **SEC-6 — Admin 2FA not enforced by default & toggleable by any admin** —
  `app/Http/Middleware/EnsureAdminTwoFactorVerified.php:23-30`, `AdminController.php:169-189`
  Middleware reads `admin_2fa_enforced` with default `false`; any admin can flip it off (also controls
  `registration_open`).
  **Fix:** default `admin_2fa_enforced` to `true`; require second admin / re-auth + current-password to disable;
  audit-log alarm on disable.
  **Done** (`899c34c6`): `enabled_default=true`; disable toggle now requires current-password re-auth +
  `AdminSecurityAuditLog::record('policy.admin_2fa.disabled')` + warning log.

#### Correctness / Data Integrity

- [x] **COR-1 — `ViewSession::isExpired()` dead status guard** — `app/Models/ViewSession.php:134-138`
  `!in_array($this->status, ['completed', 'flagged'])` where `status` is cast to `ViewSessionStatus`
  (backed enum). Non-strict `in_array` against a backed enum is always `false`, so `isExpired()` returns
  true whenever `expires_at` is past **regardless of status** — completed/flagged sessions misclassified.
  **Fix:** `!in_array($this->status, [ViewSessionStatus::Completed, ViewSessionStatus::Flagged], true)`.
  **Done** (`32b840877`, 2026-07-19): strict `in_array(..., true)` with the enum values — verified at `ViewSession.php:137`.

- [x] **COR-2 — `payout_attempts.voter_id` has no foreign key** —
  `database/migrations/2026_04_29_100000_create_payout_attempts_table.php:13`
  Declared `unsignedBigInteger(...)->index()` instead of `foreignId(...)->constrained()`. A hard-deleted
  voter leaves orphaned payout rows; `PayoutAttempt->voter()` returns null, breaking reconciliation/reporting.
  **Fix:** `foreignId('voter_id')->constrained('voters')->cascadeOnDelete()` (or `nullOnDelete` to retain
  audit rows — match `payout_run_skipped_items`).
  **Done** (`6b184201`): new migration drops the plain index, makes `voter_id` nullable, and adds
  `foreign('voter_id')->references('id')->on('voters')->nullOnDelete()` (audit-retention, not cascade —
  the only consumer already guards `if ($user->voter`). Mirrors `payout_run_skipped_items`. Split across
  two `Schema::table` closures to match the proven `fix_politician_credits` FK pattern.

- [x] **COR-3 — MySQL-only JSON syntax in SQLite-tested code** —
  `app/Http/Controllers/Standalone/PublicProfileController.php:1812` (`payload->>'$.primary_result'`),
  `app/Console/Commands/SyncPrimaryResults.php:84,86`, `app/Console/Commands/CleanCrossOfficeEcrs.php:73`
  (`JSON_EXTRACT`/`JSON_UNQUOTE`). The repo runs SQLite for tests; these paths throw on SQLite.
  **Fix:** branch on driver (as `AdminAnalyticsController:278` does) or filter decoded `payload` in PHP via `data_get()`.
  **Done** (`44947335`): all three sites branch on `DB::connection()->getDriverName()` — SQLite uses
  `json_extract(payload,'$.key')` (unquoted scalar), MySQL keeps `->>`/`JSON_UNQUOTE(JSON_EXTRACT(...))`.
  Kept as row-reducing `WHERE` filters (not PHP-side) to preserve limits. `CleanCrossOfficeEcrs --dry-run`
  confirmed on SQLite.

- [x] **COR-4 — N+1 in `PoliticalViewService::availableCampaigns`** —
  `app/Services/PoliticalViewService.php:225`
  Calls `$campaign->voterCompletedViewCount($voter->id)` (a `COUNT(*)` query) inside `->filter()` over the
  campaign list — one query per campaign.
  **Fix:** `withCount(['viewSessions as completed_count' => fn($q) => $q->where('voter_id', $voter->id)->where('status', ViewSessionStatus::Completed)])` on the eager load (line 199), read cached count in the filter.
  **Done** (`1d652319`): added `withCount(['viewSessions as voter_completed_count' => …])` to the eager-load
  chain and read `(int) $campaign->voter_completed_count` in the filter. `voterCompletedViewCount()` stays
  on the model (`voterCanWatch`, `Api/VoterController` use it elsewhere).

- [x] **COR-5 — `Api/VoterController` completion trusts client-supplied watch time** —
  `app/Http/Controllers/Api/VoterController.php:152-175`
  `trackProgress`/`completeView` (unauthenticated) trust `seconds_watched`/`total_seconds_watched` for
  payout eligibility; no ownership check that the `ViewSession` belongs to the caller.
  **Fix (as shipped):** SEC-4 binds the session to the authenticated voter (`voter.owns:session`), and
  `trackProgress`/`completeView` now derive watch time server-side via `serverAuthoritativeSeconds()`
  (clamped to wall-clock elapsed since `started_at`), discarding the client's inflated claim. (`ee7af95e`)

#### Architecture / Performance

- [x] **ARCH-1 — Batch payouts synchronous in web request** —
  `app/Http/Controllers/Standalone/AdminController.php:1844-1865` → `app/Services/PoliticalPaymentService.php:135-503`
  `POST /admin/payouts/batch-process` loops every eligible voter making live Stripe/PayPal/CashApp calls
  inline. `PayoutRun` created up front but counts only update at the end → partial runs on timeout.
  **Fix:** `ProcessBatchPayoutsJob(PayoutRun $run)`; controller creates `PayoutRun` + dispatches job, returns immediately.
  **Done** (`ea63700d`): migration adds a `status` (`pending|running|completed|failed`) + `started_at`/
  `completed_at`/`failed_at` machine to `payout_runs`. New `ProcessBatchPayoutsJob` (`timeout=600`,
  `tries=1` — no auto-retry double-pay; `PayoutAttempt.idempotency_key` is the manual re-dispatch safety
  net). `PoliticalPaymentService` split into `createPayoutRun()` + `processBatchPayoutsForRun(PayoutRun)`;
  counts now `increment()` per voter so partial runs leave accurate counters. Web + API entry points
  dispatch the job and return a queued response; the scheduled `payouts:process-viewer` command stays
  synchronous (runs on the worker, not the web request). New `ProcessBatchPayoutsJobTest` covers status
  transitions, per-voter counter increments, re-dispatch idempotency, per-voter-failure-skips-not-fails,
  and the `failed()` worker-death hook. **Deferred:** the `dispatchVoterPayout()` de-dup of the processor
  cascade vs `forcePayBelowMinimum` — orthogonal to the timeout, tracked separately.

- [x] **ARCH-2 — Dead policy map in `AuthServiceProvider`** — `app/Providers/AuthServiceProvider.php:13-16`
  Maps `App\Models\Campaign`/`App\Models\AdAssignment` to `App\Policies\*` — none of these classes exist
  (real model is `PoliticalCampaign`, no `app/Policies/` dir). Zero controllers call `authorize()`/`Gate`.
  **Fix:** delete stale map; create real policies (`PoliticalCampaignPolicy`, `VoterPolicy`,
  `ViewSessionPolicy`, `CitizenCampaignPolicy`); replace hand-rolled `abort_unless` ownership checks.
  **Done** (`03700d81`, map deletion only): dropped the four dead `use` lines + the `$policies` array;
  kept an empty `boot()` calling `registerPolicies()`. Non-breaking (lazy resolution, zero `authorize()`).
  Creating real policies + migrating `abort_unless` to `authorize()` is deferred as a separate refactor.

- [ ] **ARCH-3 — No static analysis / Pint config / coverage floor** —
  `composer.json` (no PHPStan/Larastan), no `phpstan.neon`, no `pint.json`, `.github/workflows/tests.yml:59`
  produces coverage but no `--min` threshold.
  **Fix:** add larastan level 5+, `pint.json` preset, CI lint step, `pest --coverage --min=N`.

---

### 🟠 Medium Severity

#### Controllers

- [ ] **CTL-1 — Fat controllers bypassing existing services** —
  `PublicProfileController.php` (2060), `PoliticianController.php` (2016), `AuthController.php` (1006),
  `VoterController.php` (832), `AdminAnalyticsController.php` (1352). Civic-data integration, video/S3,
  Stripe setup-intents, billing, and analytics aggregation live in controllers despite `GoogleCivicVoterInfoService`,
  `MediaStorageService`, `CongressGovService` existing.
  **Fix:** extract `PublicDirectoryService`/`DistrictDiscoveryService`, `AnalyticsReportService`,
  split `PoliticianController` into `PoliticianCampaign/Billing/MediaController`, `RegistrationService`.

- [ ] **CTL-2 — `ManagesVoterAuxiliaryActions` is an 840-line god-trait** —
  `app/Http/Controllers/Standalone/Concerns/ManagesVoterAuxiliaryActions.php`
  Bundles dashboard, earnings, referrals, Early-bank SSO, KYC upload, password update, etc. — effectively a
  second controller hidden as a trait (only `VoterController` uses it).
  **Fix:** split into focused traits or move into services (`VoterKycService`, `VoterReferralService`); keep the trait for genuinely shared helpers.

- [ ] **CTL-3 — Pervasive inline `$request->validate()`** — only 10 Form Requests for ~45 controllers;
  the largest controllers are the least likely to use them. Payment/upload rules (most security-sensitive)
  are inline.
  **Fix:** convert endpoint rules to Form Requests (centralizes `authorize()` + `rules()`).

- [ ] **CTL-4 — Ad-hoc JSON response shapes** — only 4 API Resources; `VoterController::progressHeartbeat`
  returns 3 different shapes from one endpoint; `ok`/`success`/`message`/`error`/`status` mixed.
  **Fix:** shared `ApiResponse` envelope; `CampaignSummaryResource`, `ReferralResource`; move billing JSON
  from `PoliticianController` into the underused `Api/BillingController`.

- [ ] **CTL-5 — S3 temporary-URL generation duplicated** — `VoterController::resolvePlayableMediaUrl:51-98`
  and `PoliticianController::showCampaign:535-557` both reimplement S3 URL parsing + `temporaryUrl`.
  `MediaStorageService` already exists.
  **Fix:** `MediaStorageService::temporaryUrlForCampaign(PoliticalCampaign): ?string` used by both.

- [x] **CTL-6 — Registration side-effect block triplicated** — formerly `AuthController.php:362-428,509-576,652-768`
  (politician/citizen/voter). Referral-resolution `Voter::where('referral_code')... ?? Politician::...`
  copy-pasted verbatim ×3.
  **Fix:** `RegistrationService::register(array $input, string $role): User` + `ReferralResolver`.
  **Done** (auth refactor, 2026-07-20): `AuthController` deleted; the ×3 referral-resolution copy-paste is
  now single-sourced in `ReferralService::resolveReferrerIds()` (called by all three `register*` methods in
  `RegistrationController`), and the side-effects (welcome email, phone OTP, admin notify) moved to shared
  private helpers. The three role-specific `register*` methods remain separate rather than collapsed into
  one `RegistrationService::register`, but the duplication this finding called out is resolved. Commits:
  `bd6f63d5` (route rewire + `AuthController` deletion), `82a880ad` (`UserRoleService`).

#### Models / DB

- [ ] **DB-1 — `referral_earnings` FK delete semantics silently flipped** —
  `database/migrations/2026_02_22_000001_add_politician_referral_dual_commission.php:74-80`
  Drops `cascadeOnDelete` on `referred_voter_id`/`view_session_id`, re-adds as `nullOnDelete` — severs audit
  link on voter delete, undocumented in the migration header.
  **Fix:** if retention is intended, document it; else restore cascade.

- [ ] **DB-2 — Status columns half-migrated to enums** — `Politician` (`kyc_status`,
  `verification_status`, `profile_photo_status`, `term_status`), `User` (`kyc_status`, `user_type`) use raw
  strings/const arrays. `Campaign*`/`View*` already enum-backed. `term_status` has a MySQL-only `CHECK` so
  app and DB whitelists can drift.
  **Fix:** add backed enums (`KycStatus`, `UserType`, `TermStatus`, `ProfilePhotoStatus`), cast in `casts()`.

- [ ] **DB-3 — Index-defeating `UPPER()`/`LOWER()` wrappers on filtered columns** — pervasive in
  `PublicProfileController`, `Api/MapStateCandidatesController`, `AdminAnalyticsController:483`, ~12 console
  commands. Data is already normalized to uppercase in `Politician::boot()`, so the wrappers are defensive
  but kill index use. `politicians` index is `[governance_level, state]` (doesn't serve `state`-only filters).
  **Fix:** drop function wrappers (rely on boot normalization) or add a standalone `state` index / generated `state_upper` column.

- [ ] **DB-4 — `Voter::canViewToday()` lazy-loads `User`** — `app/Models/Voter.php:196` lacks the
  `relationLoaded('user')` guard its sibling `isLegacyVerificationHolder()` has (`:218`); called per-voter in loops.
  **Fix:** guard with `relationLoaded('user')` or require callers to eager-load `user`.

#### Services / Duplication

- [ ] **DUP-1 — Payout processor-dispatch logic duplicated** —
  `app/Services/PoliticalPaymentService.php:237-443` vs `:540-641` (`processBatchPayouts` vs `forcePayBelowMinimum`)
  re-implement processor selection + the session-update transaction; the force-pay path already drifts (skips
  `PayoutAttempt` events + `recordSkippedPayout`).
  **Fix:** extract `dispatchPayoutForVoter(Voter, amount, processor, batchId)` + `markSessionsPaid()` helper.

- [ ] **DUP-2 — Fraud flag/clear logic duplicated, bypassing the service** —
  `AdminFraudController.php:78-89,105-108` vs `app/Services/FraudPreventionService.php:177-243`
  Controller mutates `flagged_for_fraud`/`trust_score` directly; the service also emits the audit log — so
  admin clears never reach `fraud_signals`/the service log.
  **Fix:** inject `FraudPreventionService`; call `clearFlag()`/`flagVoter()`.

- [ ] **DUP-3 — KYC/verification status smeared across 3 layers** —
  `AdminKycController.php:65-86,121-142`, `StripeConnectService.php:411-419`, `ProfileVerificationService`
  each set `kyc_status`/`is_verified`/`stripe_account_status` independently with no ordering guarantee;
  Stripe demotes `is_verified` on revocation while admin promotes it on approval → can overwrite each other.
  **Fix:** `KycService`/`VerificationStatusService` owning all writes (`approve`/`reject`/`syncFromStripe`/`syncFromIdme`).

- [ ] **DUP-4 — Referral-earnings query duplicated across 5 sites** —
  `PoliticalViewService.php:295`, `ManagesVoterAuxiliaryActions.php:345`, `PoliticianController.php:1753`,
  `Api/VoterController.php:206`, `AdminAnalyticsController.php:173`.
  **Fix:** accessors on `Voter`/`Politician` or `ReferralEarningService::summaryFor($model)`.

- [ ] **DUP-5 — Domain events have zero listeners** — `app/Events/` (11 classes), no `app/Listeners/`,
  no `EventServiceProvider::$listen`. Events are broadcast-only (Reverb DTOs); side-effects (notifications,
  emails, webhooks, audit logs) inlined in services/controllers.
  **Fix:** register queued listeners for existing events; move notification/email/webhook calls out of services.

- [ ] **DUP-6 — `Mail::send` (synchronous) in 12 places that should queue** —
  `AuthController.php:407,421,555,569,748,761`, `ProfileClaimController.php:68,112`,
  `CampaignBillingService.php:617,683`, `ProfileVerificationService.php:104`,
  `StandardNotificationService.php:27`. Rest of codebase uses `->queue()`.
  **Fix:** switch to `->queue()` (Mailables exist) or queued listeners.

- [ ] **DUP-7 — `CampaignBillingService` mixes mail into billing** —
  `app/Services/CampaignBillingService.php:582-715` (synchronous receipt emails).
  **Fix:** Mailable + queued listener on `CreditsPurchased`/`CreditsRefunded` events.

- [ ] **DUP-8 — `seedUnverifiedPoliticianProfile` runs Artisan synchronously** —
  `AdminImportController.php:76` (sibling `importCandidatesFromOcr:131` correctly dispatches a job).
  **Fix:** wrap in a queued job.

- [ ] **DUP-9 — Dead/decorative contracts** — `app/Contracts/AuthServiceInterface.php` (no implementation,
  no binding), `NotificationServiceInterface` (implemented but never bound/type-hinted).
  **Fix:** implement + bind, or delete. Missing `PayoutProcessor` strategy contract for the 3 payment backends.
  **Partial** (auth refactor, 2026-07-20): `AuthServiceInterface` deleted (it was never implemented/bound).
  Still open: `NotificationServiceInterface` is `implements`-ed by `StandardNotificationService` but never
  bound in a provider / type-hinted by consumers, and the `PayoutProcessor` strategy contract is not added.

#### Testing / Quality

- [ ] **TEST-1 — Critical services untested at unit level** — `GoogleCivicService`/`GoogleCivicVoterInfoService`
  (zero tests), `StripeConnectService` (only mocked as collaborator), `StripePaymentServiceTest` asserts only
  exception paths ("we can only test the exception path"), cents conversion never verified.
  **Fix:** unit tests with `Http::fake()` / mocked `StripeClient` asserting payload shape + arithmetic.

- [ ] **TEST-2 — Admin controller split outpacing its tests** — `AdminKycController`, `AdminFraudController`,
  `AdminPayoutController`, `AdminEmailTemplateController`, `AdminOfficeProfileController`,
  `AdminOnboardingController`, `AdminCandidateMatchController` have no/thin feature tests. The
  `refactor/admin-controller-split` branch is landing 15+ controllers without coverage.
  **Fix:** feature tests per admin controller (state transitions, rbac, notifications).

- [ ] **TEST-3 — Referral attribution asserted-into-oblivion** —
  `tests/Unit/Services/PoliticalViewServiceTest.php:407-434` asserts `referral_commission` is **always 0.00**;
  no test verifies a referrer actually receives the commission via `PoliticalPaymentService:701`.
  **Fix:** integration test: referred voter + completed view + payout run → assert commission lands on referrer's ledger.

- [ ] **TEST-4 — Silent stubs in `StandardNotificationService`** — `app/Services/StandardNotificationService.php:49-71`
  Six `TODO: Implement` markers for Twilio SMS / Firebase push; methods return silently, so callers believe
  notifications were sent (voter engagement SMS/push are no-ops in prod).
  **Fix:** gate behind configured flag + log warning, or throw when invoked without credentials.

- [ ] **TEST-5 — Custom exceptions are bare markers** — `app/Exceptions/{StripeConnect,CashAppPayout,
  OcrCandidateImport,PoliticianFetcher}Exception.php` extend `RuntimeException` with no `render()`/`report()`;
  payout-critical ones surface as generic 500s.
  **Fix:** implement `render()` (JSON `{error}` for API payout paths) or register renderable callbacks in `bootstrap/app.php`.

- [ ] **TEST-6 — Only 9 factories for 45 models** — missing `PayoutAttempt`, `PayoutRun`, `ViewSession`,
  `CampaignTransaction`, `FraudSignal`, etc. (tests construct inline with `::create([...])`).
  **Fix:** add factories; decouple tests from raw schemas.

#### Security (medium)

- [x] **SEC-7 — Login endpoints lack rate limiting** — `routes/standalone.php:53,57` (`POST /login`,
  `POST /admin/login`) in `guest` group with no `throttle:` (only 2FA challenge routes are throttled).
  **Fix:** `throttle:5,1` or a `LoginRateLimiter` keyed on email + IP.

- [ ] **SEC-8 — TOTP has no replay protection** — `app/Services/TwoFactorService.php:62-83`
  Same 6-digit code reusable within its validity window.
  **Fix:** cache last verified timestep per user; reject equal-or-older.

- [ ] **SEC-9 — `EarlyBankController::voter-by-email` broad blast radius** —
  `app/Http/Controllers/Api/EarlyBankController.php:167-192` — single static token maps any email → voter UUID.
  Middleware uses `hash_equals` (good), but one token = mass enumeration if leaked.
  **Fix:** rotate periodically, scope separate from webhook auth, rate-limit per-caller + alert on volume spikes.

---

### 🟡 Low Severity (cleanup, no separate fix required if touched in passing)

- **DB-L1** — Redundant indexes: `voters` has `unique('referral_code')` + separate `index('referral_code')`
  (`2026_02_05_000001:50,67`); `voter_favorite_politicians` has `unique([voter_id,politician_id])` + redundant
  `index('voter_id')` (`2026_07_01_000003:34,37`). Drop the duplicate plain indexes.
- **DB-L2** — `campaign_audit_logs` declares `timestamps()` but model sets `UPDATED_AT = null` → `updated_at`
  always NULL. Use `$table->timestamp('created_at')->useCurrent()` only.
- **DB-L3** — `FK` columns rely on MySQL auto-indexing; SQLite (tests) doesn't auto-index → test full-scans.
- **DB-L4** — Soft deletes entirely unused; hard-delete is the norm. Consider `SoftDeletes` on `Voter`/`Politician`
  if financial audit retention matters (compounds COR-2).
- **CTL-L1** — `AdminAnalyticsController::moderateQuestion` mixes `validate()` (JSON 422) with `abort_unless`
  (text 422) → inconsistent error shapes. Use a `ModerateQuestionRequest`.
- **CTL-L2** — `Schema::hasTable('onboarding_handoff_events')` at runtime (`AdminAnalyticsController:190`)
  masks a migration/ops problem with empty stats. Remove the guard; deploy the migration.
- **CTL-L3** — Three overlapping search builders in `AdminAnalyticsController` (`applyCampaignLedgerSearch:70`,
  `applyCampaignTransactionSearch:93`, `applyAccountFundingSearch:114`). Extract `applyTransactionSearch(Builder, string, array)`.
- **DB-L5** — `ViewSession::scopeActive` uses raw strings `['assigned','in_progress']` (`:144`) instead of enum values.
- **DUP-L1** — `RefundCompletedNotification` is not queued (others are). Add `implements ShouldQueue`.
- **DUP-L2** — `CampaignStatusNotifier` good but underused; `AdminCampaignController:487` sends mail directly. Route through it.
- **SEC-L1** — `.env.example:42` `BROADCAST_CONNECTION=reverb` but CI just `cp .env.example .env`; add `BROADCAST_CONNECTION=log` to `phpunit.xml` env override.
- **TEST-L1** — `WebhookTest` mocks `parseWebhook`, bypassing signature verification; add one test with a real signed payload.
- **TEST-L2** — Controller naming split: resource verbs vs domain-noun methods. Document the convention.
- **POS** — Clean baselines verified: no `$guarded=[]`, no `$request->all()` mass assignment, all `whereRaw`
  uses `?` bindings (no SQLi), 2FA secrets/recovery codes encrypted + hidden, Stripe/PayPal webhook signatures
  verified, Id.me `state` validated with `hash_equals`, no `dd()`/dead code in controllers/services, `.env.example`
  otherwise thorough (237 lines, documented).

---

## Patterns Observed (cross-cutting)

1. **Services exist but controllers bypass them.** ~50 service classes, yet the biggest controllers
   re-implement civic-data, media, and verification logic inline. The layering is *present in the service
   directory but not enforced in controllers*. The admin controllers post-split delegate correctly; the
   voter/politician/auth controllers do not.
2. **Authorization is middleware-only + hand-rolled `abort_unless`; the policy system is dead.** This is
   the single biggest architectural gap — and it produced a real IDOR (SEC-4) and admin-API 2FA bypass (SEC-5).
3. **Domain events are half-built — broadcast-only with zero listeners.** 11 event classes, no
   `EventServiceProvider::$listen`, no `app/Listeners/`. Side-effects are inlined in services/controllers.
4. **Queue discipline is "queue it when it crashed Railway," not a default.** Enrichment/OCR/video is
   queued; payouts (live external transfers) and registration emails are synchronous.
5. **Two-tier MySQL/SQLite targeting is first-class in migrations but broken in runtime code.** ~9
   migrations branch on driver; `PublicProfileController`/`SyncPrimaryResults` use MySQL-only JSON syntax.
6. **Status lifecycles half-migrated to enums.** Campaign/View fully enum-backed; User/Politician status
   columns still raw strings — the enum layer was proven but never extended.
7. **The admin-controller split is outpacing its test scaffolding.** 15+ new `Admin*Controller` files,
   ~6 have feature tests. The refactor should land tests alongside each split.
8. **Money-critical services are mocked, not unit-tested.** Stripe/PayPal/CashApp transfer payloads and
   cents arithmetic are unverified — a real risk for a payouts platform.
9. **Route map is split across two files with inconsistent grouping and middleware coverage.**
   `routes/standalone.php` holds all web routes (guest, voter, citizen, politician, admin) in a single file
   — 2,000+ lines mixing `Route::middleware('guest')`, `Route::middleware('auth')`, and
   `Route::middleware('admin')` groups with overlapping concerns. `routes/api.php` holds the
   `/api/v1` routes but the auth/middleware boundary is inconsistent: voter routes sit outside
   `auth:sanctum` (was unauthenticated before SEC-4; now use custom `voter-token`/`voter.owns`
   middleware rather than Sanctum), while admin API routes under `/api/v1/admin/*` use
   `auth:sanctum` + `role:admin` + `admin.2fa` (post SEC-5). The split means:
   - **Web admin routes** are in `standalone.php` under `admin.2fa`/`role:admin` middleware.
   - **Web voter/politician routes** are in `standalone.php` under `auth` middleware with
     hand-rolled `abort_unless` ownership checks (no policies — see pattern 2).
   - **API voter routes** (`/api/v1/voters/*`) use `voter-token` + `voter.owns` middleware
     (post SEC-4), but registration/profile endpoints remain public.
   - **API admin routes** (`/api/v1/admin/*`) use `auth:sanctum` + `role:admin` + `admin.2fa`
     (post SEC-5).
   - **Login/auth routes** (`POST /login`, `POST /admin/login`) are in the `guest` group with
     `throttle:login` (post SEC-7), but 2FA challenge routes are in a separate throttled group.
   - **Map/public routes** (`/map`, state/candidate lookup via `MapStateCandidatesController`)
     sit in the public/unauthenticated group in `standalone.php`.
   - **Stripe/PayPal webhooks** (`/webhooks/stripe`, `/webhooks/paypal`) are in `routes/api.php`
     outside any auth middleware (correct — signature-verified), but also outside rate limiting.
   The naming convention is inconsistent: some routes use resource verbs
   (`Route::resource`, `Route::apiResource`), others use domain-noun custom methods
   (`AdminController@processBatchPayouts`, `VoterController@trackProgress`) — see TEST-L2.

---

## Refactor Roadmap

Ordered by risk: **security first, then correctness bugs, then architecture, then testing/tooling.**
Each phase is independently shippable. Tick boxes as work lands; note commit hash.

### Phase 0 — Security hotfixes (do now, before any merge)
- [ ] SEC-1 — Rotate & purge PayPal credentials from git history + rotate in dashboard + audit logs _(partially done: `.env.example` blanked in `37fa13e2`; history purge + rotation + audit remain — user/ops)_
- [x] SEC-2 — Move KYC docs off the public disk + serve via admin controller only — `45cd633d`
- [x] SEC-3 — Derive KYC stored extension from detected MIME (`guessExtension()`) — `0280d7ea`
- [x] SEC-4 — Authenticate the voter API (per-voter bearer tokens, NOT sanctum+VoterPolicy) + strip PII from `VoterResource` — `4401c558` _(BREAKING)_
- [x] SEC-5 — Enforce 2FA on admin API routes — `8728abb8`
- [x] SEC-6 — Default `admin_2fa_enforced=true`; gate the disable toggle — `899c34c6`
- [x] SEC-7 — Add rate limiting to login routes — `e9a2f30f`
- [x] COR-5 — Bind view-completion to authenticated voter identity (depends on SEC-4 auth) — `ee7af95e`

### Phase 1 — Correctness & data-integrity bugs
- [x] COR-1 — Fix `ViewSession::isExpired()` enum compare (strict `in_array`) — `32b840877`
- [x] COR-2 — Add FK to `payout_attempts.voter_id` (nullOnDelete, audit retention) — `6b184201`
- [x] COR-3 — Replace MySQL-only JSON syntax with driver-branch — `44947335`
- [x] COR-4 — Eager-load `withCount` to fix `PoliticalViewService` N+1 — `1d652319`
- [ ] DB-1 — Document or restore `referral_earnings` delete semantics
- [ ] DB-4 — Guard `Voter::canViewToday()` against lazy `user` load

### Phase 2 — Finish & extend the controller split
- [x] Step 7 (analytics + dashboard split — routes wired, verified) — `845ddf19`
- [x] Step 8 (settings split — `AdminSettingsController` extracted, `AdminController` retired) — `a18d1641`
- [ ] **AdminController split is now COMPLETE (13 domain controllers).** Remaining Phase 2 items are the broader controller-layer cleanups below.
- [ ] CTL-1 — Extract services from `PublicProfileController` / `PoliticianController` / `AuthController`
- [ ] CTL-2 — Decompose the `ManagesVoterAuxiliaryActions` god-trait
- [ ] CTL-3 — Convert inline validation to Form Requests (prioritize payment/upload endpoints)
- [ ] CTL-4 — Shared JSON envelope + API Resources; move billing JSON to `Api/BillingController`
- [ ] CTL-5 / CTL-6 / DUP-4 — De-duplicate S3 URL gen, registration flow, referral-earnings query
  - [x] CTL-6 (registration referral-resolution) — done via `ReferralService` in the auth refactor (`bd6f63d5`); see finding above
- [x] ARCH-2 — Delete dead policy map — `03700d81` _(creating real policies + adopting `authorize()` is deferred — see finding)_

### Phase 3 — Service-layer & event pipeline
- [x] ARCH-1 — `ProcessBatchPayoutsJob` (async batch payouts) — `ea63700d` _(deferred: `dispatchVoterPayout` de-dup, see finding + DUP-1)_
- [ ] DUP-1 — Extract `dispatchPayoutForVoter` / `markSessionsPaid` in `PoliticalPaymentService`
- [ ] DUP-2 — Route fraud flag/clear through `FraudPreventionService`
- [ ] DUP-3 — Introduce `KycService` owning all verification-status writes
- [ ] DUP-5 — Register queued listeners for the 11 existing domain events
- [ ] DUP-6 / DUP-7 / DUP-L1 / DUP-L2 — Queue all mail; move receipts to listeners
- [ ] DUP-8 — Queue the unverified-profile Artisan call
- [ ] DUP-9 — Implement/delete dead contracts; add `PayoutProcessor` strategy contract
  - [x] `AuthServiceInterface` deleted (auth refactor, `bd6f63d5`); `NotificationServiceInterface` still unbound, `PayoutProcessor` contract still missing

### Phase 4 — DB & enum cleanup
- [ ] DB-2 — Backed enums for `KycStatus`/`UserType`/`TermStatus`/`ProfilePhotoStatus`
- [ ] DB-3 — Remove index-defeating `UPPER()`/`LOWER()` wrappers; add `state` index
- [ ] DB-L1 / DB-L2 / DB-L5 — Drop redundant indexes, fix `updated_at` column, use enum values in scopes
- [ ] DB-L4 — Decide on `SoftDeletes` for audit retention

### Phase 5 — Testing & tooling
- [ ] ARCH-3 — Add Larastan (level 5+), `pint.json`, CI lint step, coverage floor
- [ ] TEST-1 — Unit tests for GoogleCivic / StripeConnect / StripePayment (payload + arithmetic)
- [ ] TEST-2 — Feature tests for every `Admin*Controller` (land alongside Phase 2 splits)
- [ ] TEST-3 — Integration test proving referral commission reaches the referrer
- [ ] TEST-4 — Implement or gate the `StandardNotificationService` stubs
- [ ] TEST-5 — `render()`/`report()` on custom payment exceptions
- [ ] TEST-6 — Factories for the remaining 36 models
- [ ] SEC-L1 / TEST-L1 — `BROADCAST_CONNECTION=log` in test env; signed-payload webhook test

---

## Verification Environment Constraints

Discovered while attempting to run the test suite for refactor verification (2026-07-15).
These block `composer test` / `vendor/bin/pest` in this environment — record here so we
don't re-discover them each session:

1. **`/tmp/php-opcache` must exist.** `/opt/homebrew/etc/php/8.5/php.ini` sets
   `opcache.file_cache=/tmp/php-opcache`; if the dir is absent, every `php` call fatal-errors
   at startup. Fix: `mkdir -p /tmp/php-opcache && chmod 777 /tmp/php-opcache` (may not persist
   across reboots).
2. **`artisan` / `composer` / `pest` hang inside the Bash sandbox.** Laravel's bootstrap makes
   a network call the sandbox silently drops. Run all verification commands with the sandbox
   **disabled** (artisan then runs instantly).
3. **There is no `php artisan test`.** The test runner is `vendor/bin/pest` (the composer `test`
   script's `artisan test` line is effectively dead).
4. **Pest autoloader is broken** — `vendor/bin/pest` throws `Class "Pest\TestSuite" not found`;
   `composer dump-autoload` hangs (never completes) in this environment. **However `php artisan test`
   runs the full suite reliably** when the sandbox is disabled (626 passed, 7 risky, 0 failed on
   2026-07-20, ~13s). Use `php artisan test` (sandbox-disabled) as the primary verification; the earlier
   "suite not runnable here" note is stale.

> **Reliable per-step verification (works now):** `php -l <file>` + `php artisan test`
> (sandbox-disabled, full or `--filter`). `php artisan route:list` also works for wiring checks.

---

## Progress Log

| Date | Phase | Item | Commit/PR | Notes |
|------|-------|------|-----------|-------|
| 2026-07-15 | — | Audit performed | — | 5 parallel audits; tracker doc created |
| 2026-07-15 | 2 | Step 7 (analytics+dashboard split) — controllers extracted, routes rewired, verified | `845ddf19` | `AdminAnalyticsController`/`AdminDashboardController` extracted, `routes/standalone.php` rewired, `route:list` + `php -l` clean |
| 2026-07-15 | — | Codebase audit tracker doc committed | `196e0292` | `CODEBASE_AUDIT.md` added to repo root |
| 2026-07-15 | 2 | Step 8 (settings split) — `AdminSettingsController` extracted, `AdminController` retired | `a18d1641` | Admin controller split COMPLETE (13 domain controllers); 9 settings/platform-settings routes rewired; `PaymentModeFilterable` dropped (dead here); `route:list` + `php -l` clean |
| 2026-07-15 | 0 | SEC-3 — KYC extension from detected MIME (`guessExtension()` + allowlist→`bin`) | `0280d7ea` | `PoliticianController::uploadKycDocument`; `php -l` clean |
| 2026-07-15 | 0 | SEC-7 — rate-limit login endpoints (5/min keyed email+IP) | `e9a2f30f` | `AppServiceProvider` `RateLimiter::for('login')`; `throttle:login` on `POST /login` + `/admin/login` |
| 2026-07-15 | 0 | SEC-6 — admin 2FA enforced by default + gated disable | `899c34c6` | `config/platform.php` `enabled_default=true`; disable requires current-password re-auth + audit log; settings.blade password field |
| 2026-07-15 | 0 | SEC-4 — voter API per-voter bearer-token auth + PII strip | `4401c558` | NEW: `voter_api_tokens` table, `VoterApiToken`, `voter-token`/`voter.owns` middleware, token issued at registration + `/token/rotate`; `VoterResource` email/phone removed. **BREAKING** (consumers must adopt tokens). Did NOT use Sanctum (not installed; API voters `user_id=NULL`). Verified `php -l` + autoload + static wiring; `route:list` blocked by env boot-hang |
| 2026-07-15 | 0 | SEC-5 — enforce admin 2FA on `/api/v1/admin/*` | `8728abb8` | `admin.2fa` applied to admin API group; `EnsureAdminTwoFactorVerified` returns 403 JSON for `expectsJson()` |
| 2026-07-15 | 0 | COR-5 — server-authoritative view watch-time | `ee7af95e` | `VoterController::trackProgress`/`completeView` clamp client claim to wall-clock elapsed since `started_at` (`serverAuthoritativeSeconds()`); inflated claims logged |
| 2026-07-15 | 0 | Phase 0 merged to master (local, unpushed) | `169f45d2` | `fix/security-hotfixes` → master `--no-ff`; 7 commits ahead of `origin/master`; SEC-1/SEC-2 deferred (user/ops). Tests still not runnable here |
| 2026-07-19 | 1 | COR-1 — strict enum compare in `ViewSession::isExpired()` | `32b840877` | `in_array(..., [ViewSessionStatus::Completed, ViewSessionStatus::Flagged], true)`; shipped in "updates to the map and other quality of life fixes" — not logged at the time |
| 2026-07-20 | — | Auth refactor (Phases 1–7 of `doc/AUTH_REFACTOR_PLAN.md`) | `bd6f63d5`, `82a880ad`, `7ec7170c`, `60eeab81` | Deleted dead Breeze code + `AuthServiceInterface` (DUP-9 partial); consolidated TOTP into one `TwoFactorService`; `UserRoleService` as single role source of truth (`User::isAdmin/isCitizen` routed through it); split `AuthController` into `Login/Registration/PasswordReset/PhoneVerification/EmailVerification/AdminTwoFactorController` + extracted `ReferralService` (CTL-6 done); voter API auth docs (`doc/auth-architecture.md`); dedicated `two_factor.session_ttl_minutes` config key. Full suite 613 passed, 0 failed. See `doc/AUTH_REFACTOR_PROGRESS.md` |
| 2026-07-20 | 2 | ARCH-2 — delete dead policy map in `AuthServiceProvider` | `03700d81` | Dropped the four dead `use` lines + `$policies` map (referenced non-existent `Campaign`/`AdAssignment`/`CampaignPolicy`/`AdAssignmentPolicy`); kept empty `boot()` calling `registerPolicies()`. Non-breaking (zero `authorize()`/Gate usage). Real policies + `authorize()` migration deferred |
| 2026-07-20 | 1 | COR-4 — eager-load completed-view count (N+1) in `availableCampaigns` | `1d652319` | `withCount(['viewSessions as voter_completed_count' => …])` on the eager load; filter reads `(int) $campaign->voter_completed_count`. `voterCompletedViewCount()` retained on the model for `voterCanWatch` + `Api/VoterController` |
| 2026-07-20 | 1 | COR-3 — driver-branch JSON syntax (SQLite tests) | `44947335` | `PublicProfileController:1812`, `SyncPrimaryResults:84,86`, `CleanCrossOfficeEcrs:73` branch on `DB::connection()->getDriverName()` — SQLite `json_extract(...)` vs MySQL `->>`/`JSON_UNQUOTE(JSON_EXTRACT(...))`. Kept as row-reducing WHERE filters. `clean-cross-office-ecrs --dry-run` confirmed on SQLite |
| 2026-07-20 | 0 | SEC-2 — KYC docs moved to the `local` disk | `45cd633d` | Upload delete + `storeAs` + all 3 viewers (politician/voter/admin) now read/write `Storage::disk('local')`; one-time migration moves `storage/app/public/kyc` → `storage/app/private/kyc` (idempotent, merges). `kyc_document_path` unchanged (relative). Admin route already admin-gated |
| 2026-07-20 | 1 | COR-2 — `nullOnDelete` FK on `payout_attempts.voter_id` | `6b184201` | New migration: drop index, make nullable, add `foreign('voter_id')->references('id')->on('voters')->nullOnDelete()`. Mirrors `payout_run_skipped_items`; split across two `Schema::table` closures (proven FK-fix pattern) |
| 2026-07-20 | 3 | ARCH-1 — async batch payouts via queued job + run status machine | `ea63700d` | Migration adds `status`/`started_at`/`completed_at`/`failed_at` to `payout_runs`. New `ProcessBatchPayoutsJob` (`timeout=600`, `tries=1`). `PoliticalPaymentService` → `createPayoutRun()` + `processBatchPayoutsForRun(PayoutRun)`; counts `increment()` per voter. Web + API entry points dispatch + return queued response; scheduled command stays sync. New `ProcessBatchPayoutsJobTest` (4 cases). `dispatchVoterPayout` de-dup deferred (see DUP-1). **Full suite 626 passed, 7 risky, 0 failed** |

<!--
Append rows here as work ships. When a roadmap checkbox is completed, tick it above AND log the commit here.
-->