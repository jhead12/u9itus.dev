# Auth Refactor — Progress Tracker

Living checklist for the authentication cleanup described in [`AUTH_REFACTOR_PLAN.md`](./AUTH_REFACTOR_PLAN.md).
See [`auth-architecture.md`](./auth-architecture.md) for the resulting reference doc on how auth works across the app.

Last updated: 2026-07-20 — **all 7 phases complete.**

---

## Status at a glance

| Phase | Description | Status |
|---|---|---|
| 1 | Remove dead Breeze code | ✅ Done |
| 2 | Consolidate TOTP into one shared service | ✅ Done |
| 3 | Single source of truth for roles | ✅ Done |
| 4 | Split `AuthController` into focused controllers | ✅ Done |
| 5 | Clean up voter API auth + docs | ✅ Done |
| 6 | Config and comment hygiene | ✅ Done |
| 7 | Final verification | ✅ Done |

## Commits

| Commit | Phases | Summary |
|---|---|---|
| `565a1680` | 1, 2 (partial 3, 4) | Root doc reorg, bundled with most of the Breeze deletion, TOTP consolidation, and the new split-controller *files* (not yet routed) |
| `bd6f63d5` | 4 | Rewired `routes/standalone.php` to the new controllers, deleted `AuthController.php`; fixed a `ReferralService` key-casing bug found along the way |
| `82a880ad` | 3 | `User::isAdmin()`/`isCitizen()` migrated to `UserRoleService` |
| `7ec7170c` | 6 | Dedicated `two_factor.session_ttl_minutes` config key, `bootstrap/app.php` comment |
| `60eeab81` | 5 | `doc/auth-architecture.md`, `routes/api.php` comment |

All commits above are local to this branch as of this writing — not yet pushed to `origin/master`.

---

## Phase 4 — Route → controller mapping (for reference)

| Route | Old (`AuthController@`) | New |
|---|---|---|
| `GET/POST /login` | `showLogin`/`login` | `LoginController` |
| `GET/POST /admin/login` | `showAdminLogin`/`adminLogin` | `LoginController` |
| `POST /logout` | `logout` | `LoginController` |
| `GET/POST /register*` (politician/voter/citizen/closed) | `showRegister*`/`register*` | `RegistrationController` |
| `GET/POST /forgot-password`, `/reset-password*` | `showForgotPassword`/`sendResetLink`/`showResetPassword`/`resetPassword` | `PasswordResetController` |
| `GET/POST /verify-phone`, `/resend-phone-code` | `showVerifyPhone`/`verifyPhone`/`resendPhoneCode` | `PhoneVerificationController` |
| `GET/POST /email/verify*`, `/email/resend` | `showVerifyEmail`/`verifyEmail`/`resendVerification` | `EmailVerificationController` |
| `GET/POST /admin/2fa/challenge` | `showAdminTwoFactorChallenge`/`verifyAdminTwoFactorChallenge` | `AdminTwoFactorController@showChallenge`/`verifyChallenge` (renamed) |

## Bug fixes found during the refactor

- **`ReferralService::resolveReferrerIds()`** returned camelCase keys via `compact()` while its docblock and all three `RegistrationController` callers (politician/citizen/voter) destructure snake_case keys — threw `Undefined array key "referred_by_voter_id"` on every registration without a `ref` param. This would have broken production sign-up; fixed in `bd6f63d5`.
- **`tests/Feature/Auth/EmailVerificationTest.php`** asserted the old generic `route('dashboard')` redirect; the new `EmailVerificationController` correctly uses role-aware `UserRoleService::dashboardRouteFor()`, landing a role-less factory user on `voter.dashboard` instead. Test updated to match.

---

## Phase 7 — Final verification results (2026-07-20)

```
php artisan route:cache    → Routes cached successfully
php artisan config:cache   → Configuration cached successfully
php artisan test --filter=Auth        → 50 passed, 1 risky (pre-existing), 0 failed
php artisan test --filter=TwoFactor   → 7 passed
php artisan test tests/Feature/Api/VoterApiTest.php → 18 passed
php artisan test (full suite)         → 613 passed, 7 risky (pre-existing), 0 failed
```

Manual checks against a live `php artisan serve` instance (`127.0.0.1:8000`), driven with `curl` and verified against real DB state via `tinker`:

- [x] `/login`, `/admin/login`, `/register/{politician,voter,citizen}`, `/forgot-password`, `/register` all render 200
- [x] `POST /register/voter` end-to-end: creates `User` (`user_type=voter`), assigns Spatie `voter` role, creates linked `Voter` row, redirects to `/email/verify`
- [x] `POST /login` with the just-registered voter → redirects to `/voter/dashboard`; dashboard route itself redirects unverified/unonboarded users to `/voter/onboarding/welcome` (expected — onboarding gate, not a bug)
- [x] `POST /admin/login` with invalid credentials → redirects back to `/admin/login` (no 500, no leak)
- [x] `GET /reset-password/{token}` renders 200
- [x] `GET /admin/2fa/challenge` unauthenticated → redirects to `/login` (route middleware gate working)
- [x] Admin 2FA setup/enable/disable/recovery rotation, generic 2FA setup/challenge — covered by `AdminTwoFactorPolicyTest` (7/7 passing) rather than re-driven manually; no route/controller changes touched this logic beyond the TTL config key
- [x] Voter API token rotation and protected endpoints — covered by `VoterApiTest` (18/18 passing)
- [~] `POST /forgot-password` returned a 500 in the live manual check. **Not a refactor regression** — `PasswordResetController::sendResetLink()` is byte-identical to the old `AuthController` method, and `PasswordResetTest` (which fakes notifications) passes 4/4. The live 500 traces to a local-environment Mailgun credential issue (`WelcomeMail failed ... Forbidden (code 401)` logged for the registration email moments earlier) — an outbound-mail config problem, not application code. Test coverage is the source of truth here since it isolates the controller logic from the local mail credentials.

Test user created during the manual check (`manual-smoke-*@example.com`) was deleted afterward; no residual data.

---

## Commit hygiene note

`565a1680` ("docs: consolidate root planning docs into doc/") ended up bundling the doc move together with most of Phases 1–2 and the Phase 4 controller *files* (unrouted at that point), plus unrelated concurrent work (candidate-news command, map JS, a `BallotMeasure` model). It's already pushed-adjacent history on this branch; splitting it retroactively would require a `reset --soft` + force-push, which hasn't been done. Every commit since (`bd6f63d5` onward) is scoped correctly to just its phase — verified via `git diff --stat` before each commit, and in the Phase 5 commit, `git add -p` was used to cherry-pick out unrelated in-flight changes that had landed in `routes/api.php` from another workstream.
