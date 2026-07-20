# Auth Refactor — Progress Tracker

Living checklist for the authentication cleanup described in [`AUTH_REFACTOR_PLAN.md`](./AUTH_REFACTOR_PLAN.md).
Update this file as work proceeds. **Nothing in the refactor has been committed yet** — all changes are in the working tree, currently entangled with unrelated work (candidate-news command, map JS). See *Commit hygiene* below.

Last updated: 2026-07-20

---

## Status at a glance

| Phase | Description | Status |
|---|---|---|
| 1 | Remove dead Breeze code | ✅ Done (uncommitted) |
| 2 | Consolidate TOTP into one shared service | ✅ Done (uncommitted) |
| 3 | Single source of truth for roles | ⚠️ Partial — `User.php` not migrated |
| 4 | Split `AuthController` into focused controllers | ✅ Done — routes rewired, `AuthController` deleted, tests green |
| 5 | Clean up voter API auth + docs | ❌ Not started |
| 6 | Config and comment hygiene | ⚠️ Partial — 2FA TTL key not fixed |
| 7 | Final verification | ❌ Not run |

---

## Phase 1 — Remove dead Breeze code ✅

- [x] Delete `app/Http/Controllers/Auth/*` (9 controllers)
- [x] Delete `app/Http/Requests/Auth/LoginRequest.php`
- [x] Delete `resources/views/auth/*` (6 views)
- [x] Delete `routes/auth.php`
- [x] Delete `app/Contracts/AuthServiceInterface.php`
- [ ] Verify: `php artisan route:list` shows no duplicate `login`/`logout`/`register`/`verification.*`/`password.*` names
- [ ] Verify: no references to `AuthenticatedSessionController`, `LoginRequest`, `AuthServiceInterface` remain

## Phase 2 — Consolidate TOTP ✅

- [x] Delete `app/Services/AdminTwoFactorService.php`
- [x] Extend `app/Services/TwoFactorService.php` (issuer label + logo config)
- [x] `tests/Feature/Auth/AdminTwoFactorPolicyTest.php` swapped to `TwoFactorService::class`
- [ ] Verify: `AdminController` 2FA methods inject `TwoFactorService` (not `AdminTwoFactorService`)
- [ ] Verify: no reference to `AdminTwoFactorService` remains in `app/` or `tests/`

## Phase 3 — Single source of truth for roles ⚠️

- [x] Create `app/Services/UserRoleService.php` (`resolvePrimaryRole`, `hasRole`, `repairSpatieRole`, `dashboardRouteFor`)
- [x] Simplify `app/Http/Middleware/CheckUserRole.php` to repair-then-continue
- [x] `AuthController::roleRedirect()` → `UserRoleService::dashboardRouteFor()` (verify)
- [ ] **Migrate `app/Models/User.php`** — `isAdmin()`, `isCitizen()`, `isPolitician()`, `isVoter()` still use `hasRole() || $this->user_type === '…'`. Route through `UserRoleService::hasRole()` (or a thin local wrapper). Scopes (`scopeAdmins` etc.) querying `user_type` directly are correct and stay as-is.
- [ ] Verify: `tests/Feature/Auth/AuthenticationTest.php` passes
- [ ] Verify: user with `user_type=voter` but no Spatie role reaches `voter.dashboard`
- [ ] Verify: user with only Spatie role `voter` but `user_type=null` gets repaired and reaches the right dashboard

## Phase 4 — Split `AuthController` ⚠️ (the critical gap)

- [x] Create `LoginController` (`showLogin`, `login`, `showAdminLogin`, `adminLogin`, `logout`)
- [x] Create `RegistrationController` (`showRegisterChoose`, politician/citizen/voter show+register, `showRegisterClosed`, `storeMailingListSubscriber`)
- [x] Create `PasswordResetController` (`showForgotPassword`, `sendResetLink`, `showResetPassword`, `resetPassword`)
- [x] Create `PhoneVerificationController` (`showVerifyPhone`, `verifyPhone`, `resendPhoneCode`)
- [x] Create `EmailVerificationController` (`showVerifyEmail`, `verifyEmail`, `resendVerification`)
- [x] Create `AdminTwoFactorController` (`showChallenge`, `verifyChallenge`)
- [x] Create `app/Services/ReferralService.php` + `MailingListService.php`
- [x] **Rewire `routes/standalone.php`** — all 28 `AuthController` references replaced with the new controllers per the mapping table below (2026-07-20). Stale `AuthController` mentions in `EarlyBankWebhookService` and `CampaignCrudTest` comments updated to `RegistrationController`.
- [x] **Delete `app/Http/Controllers/Standalone/AuthController.php`** — deleted after `grep -rn 'AuthController' app/ routes/ tests/ resources/` returned only the class definition. `route:list` confirms all auth routes resolve to the new controllers.
- [x] Verify: `php artisan route:list` shows all auth routes pointing at the new controllers
- [x] Verify: auth tests pass — `--filter=Auth` → 50 passed, 1 risky (pre-existing), 0 failed; `--filter=TwoFactor` → 7 passed

### Bug fixes found while completing Phase 4 (2026-07-20)
- [x] **`ReferralService::resolveReferrerIds()`** returned camelCase keys via `compact()` but its PHPDoc contract and all three callers (`RegistrationController` politician/citizen/voter) destructure snake_case keys → "Undefined array key referred_by_voter_id" 500 on every registration with no `ref` param. Fixed both return statements to emit `referred_by_voter_id` / `referred_by_politician_id`. This would have broken production registration.
- [x] **`tests/Feature/Auth/EmailVerificationTest.php`** asserted the old generic `route('dashboard')` redirect; the new `EmailVerificationController` uses role-aware `UserRoleService::dashboardRouteFor()` (intended per Phase 3), which falls back to `voter.dashboard` for a role-less factory user. Updated the assertion to `route('voter.dashboard')`.

### Route → controller mapping (Phase 4 rewiring)

| Route | Current (`AuthController@`) | New controller `@` method |
|---|---|---|
| `GET /login` | `showLogin` | `LoginController@showLogin` |
| `POST /login` | `login` | `LoginController@login` |
| `GET /admin/login` | `showAdminLogin` | `LoginController@showAdminLogin` |
| `POST /admin/login` | `adminLogin` | `LoginController@adminLogin` |
| `POST /logout` | `logout` | `LoginController@logout` |
| `GET /register` | `showRegisterChoose` | `RegistrationController@showRegisterChoose` |
| `GET /register/politician` | `showRegisterPolitician` | `RegistrationController@showRegisterPolitician` |
| `POST /register/politician` | `registerPolitician` | `RegistrationController@registerPolitician` |
| `GET /register/voter` | `showRegisterVoter` | `RegistrationController@showRegisterVoter` |
| `POST /register/voter` | `registerVoter` | `RegistrationController@registerVoter` |
| `GET /register/citizen` | `showRegisterCitizen` | `RegistrationController@showRegisterCitizen` |
| `POST /register/citizen` | `registerCitizen` | `RegistrationController@registerCitizen` |
| `GET /register/closed` | `showRegisterClosed` | `RegistrationController@showRegisterClosed` |
| `POST /register/closed` | `storeMailingListSubscriber` | `RegistrationController@storeMailingListSubscriber` |
| `GET /forgot-password` | `showForgotPassword` | `PasswordResetController@showForgotPassword` |
| `POST /forgot-password` | `sendResetLink` | `PasswordResetController@sendResetLink` |
| `GET /reset-password/{token}` | `showResetPassword` | `PasswordResetController@showResetPassword` |
| `POST /reset-password` | `resetPassword` | `PasswordResetController@resetPassword` |
| `GET /verify-phone` | `showVerifyPhone` | `PhoneVerificationController@showVerifyPhone` |
| `POST /verify-phone` | `verifyPhone` | `PhoneVerificationController@verifyPhone` |
| `POST /resend-phone-code` | `resendPhoneCode` | `PhoneVerificationController@resendPhoneCode` |
| `GET /email/verify` | `showVerifyEmail` | `EmailVerificationController@showVerifyEmail` |
| `GET /email/verify/{id}/{hash}` | `verifyEmail` | `EmailVerificationController@verifyEmail` |
| `POST /email/resend` | `resendVerification` | `EmailVerificationController@resendVerification` |
| `GET /2fa/challenge` | `showAdminTwoFactorChallenge` | `AdminTwoFactorController@showChallenge` |
| `POST /2fa/challenge` | `verifyAdminTwoFactorChallenge` | `AdminTwoFactorController@verifyChallenge` |

> Method-name change to remember: the admin 2FA challenge methods were renamed `showAdminTwoFactorChallenge` → `showChallenge` and `verifyAdminTwoFactorChallenge` → `verifyChallenge` in the new controller. Update the two route entries accordingly. All other method names are unchanged.

### Pre-delete checks for `AuthController`
- [ ] `grep -rn 'AuthController' app/ routes/ tests/ resources/` returns nothing (besides this doc) after rewiring
- [ ] No blade view does `@inject` or references `AuthController` directly

## Phase 5 — Voter API auth docs ❌

- [ ] Docblock on `app/Http/Middleware/AuthenticateVoterToken.php` explaining the non-Sanctum model
- [ ] Class-level note on `app/Models/VoterApiToken.php` re: rotation policy + no `User` relation
- [ ] Comment block above voter-token groups in `routes/api.php`
- [ ] Create `doc/auth-architecture.md` — voter widget vs. dashboard session vs. admin/politician Sanctum API
- [ ] Verify: `tests/Feature/Api/VoterApiTest.php` still passes (docs-only, no behavior change)

## Phase 6 — Config and comment hygiene ⚠️

- [x] `config/platform.php` — `features.two_factor` → `true`
- [x] `config/platform.php` — `services.auth.standalone` mapping removed (verify)
- [ ] **`app/Http/Middleware/EnsureTwoFactorVerified.php:58`** reads TTL from `platform.standalone.auth.admin_2fa.session_ttl_minutes` — a non-admin middleware using an `admin_2fa` key. Add `platform.standalone.auth.two_factor.session_ttl_minutes` and read from that.
- [ ] `bootstrap/app.php` — inline comment above `'2fa'` and `'admin.2fa'` aliases explaining the split
- [ ] Verify: `php artisan config:cache` succeeds
- [ ] Verify: no `StandardAuthService` / `AuthServiceInterface` references remain

## Phase 7 — Final verification ❌

```bash
php artisan route:cache
php artisan config:cache
php artisan test --filter=Auth
php artisan test --filter=TwoFactor
php artisan test --filter=Api/Voter
```

Manual checks:
- [ ] `/login` renders and submits
- [ ] `/admin/login` renders and submits
- [ ] `/register/politician`, `/register/voter`, `/register/citizen` work
- [ ] `/forgot-password` and reset flow work
- [ ] Admin 2FA setup, challenge, disable, recovery rotation work
- [ ] Generic 2FA setup and challenge work
- [ ] Voter API token rotation and protected endpoints work

---

## Commit hygiene (important)

The working tree currently mixes **auth-refactor changes** with **unrelated work**:

Unrelated (do NOT include in the auth commit):
- `app/Console/Commands/RefreshCandidateNews.php` (M), `CheckCandidateNewsRefreshHealth.php` (??), `CandidateNewsRefreshNotification.php` (??), `CandidateNewsRunLog.php` (??), `2026_07_20_140100_create_candidate_news_run_logs_table.php` (??)
- `app/Services/CandidateNewsService.php` (M)
- `app/Http/Controllers/Api/Map*Controller.php` (M) — 3 files
- `resources/js/map/*` (M) — 5 files, `resources/js/map/utils/` (??), `vite.analyze.config.js` (??)
- `routes/console.php` (M), `package-lock.json` (M)

Auth-refactor only (stage these explicitly when committing):
- Deleted: `app/Http/Controllers/Auth/*`, `app/Http/Requests/Auth/LoginRequest.php`, `app/Services/AdminTwoFactorService.php`, `app/Contracts/AuthServiceInterface.php`, `resources/views/auth/*`, `routes/auth.php`
- New: `app/Http/Controllers/Standalone/{Login,Registration,PasswordReset,PhoneVerification,EmailVerification,AdminTwoFactor}Controller.php`, `app/Services/{UserRole,Referral,MailingList}Service.php`
- Modified: `app/Http/Controllers/Standalone/{Auth,Admin,TwoFactor}Controller.php`, `app/Http/Middleware/CheckUserRole.php`, `app/Services/TwoFactorService.php`, `config/platform.php`, `tests/Feature/Auth/AdminTwoFactorPolicyTest.php`
- Plan/docs: `doc/AUTH_REFACTOR_PLAN.md`, `doc/AUTH_REFACTOR_PROGRESS.md`, (future) `doc/auth-architecture.md`

When ready to commit, use `git add` with explicit paths (or `git add -p`) — not `git add -A` — to keep the auth refactor in its own commit.

---

## Suggested execution order to finish

1. **Phase 4 route rewiring** (highest value, unblocks deleting `AuthController`) — edit `routes/standalone.php` per the mapping table above.
2. Delete `AuthController.php` after confirming zero references.
3. **Phase 3 finish** — migrate `User.php` role methods to `UserRoleService`.
4. **Phase 6 finish** — fix `EnsureTwoFactorVerified` TTL key.
5. **Phase 5** — voter API docs.
6. **Phase 7** — run tests + manual checks.
7. Commit auth-refactor paths only (see *Commit hygiene*).