# Auth Architecture

U9itus runs three independent authentication models side by side. This doc is
the map between them — which guard/mechanism each uses, who it applies to,
and where the code lives. See [`AUTH_REFACTOR_PLAN.md`](./AUTH_REFACTOR_PLAN.md)
and [`AUTH_REFACTOR_PROGRESS.md`](./AUTH_REFACTOR_PROGRESS.md) for the
refactor history that produced this shape.

## 1. Dashboard session auth (politicians, citizens, admins, and voters using the dashboard)

The standard Laravel session guard (`config('auth.defaults.guard')` = `web`),
cookie-based, backed by the `users` table.

- **Login/registration/password-reset/verification**: `app/Http/Controllers/Standalone/{Login,Registration,PasswordReset,PhoneVerification,EmailVerification}Controller.php`, routed in `routes/standalone.php`. These replaced Laravel's stock Breeze scaffolding, which has been deleted (Phase 1 of the auth refactor).
- **Role model**: `users.user_type` (`admin`/`politician`/`citizen`/`voter`) is the canonical source of truth. Spatie `laravel-permission` roles (`model_has_roles`) are kept in sync as a repair/cache layer, not a second source of truth. All role checks should go through `App\Services\UserRoleService` (`hasRole()`, `resolvePrimaryRole()`, `dashboardRouteFor()`) rather than checking `user_type` or Spatie directly. `App\Http\Middleware\CheckUserRole` self-heals a missing/out-of-sync Spatie role from `user_type` on every authenticated request; if neither is resolvable it logs the user out and redirects to `/register`.
- **Two-factor auth — two independent flows on the same `users` table**:
  - **Generic (voter/politician/citizen)**: columns `two_factor_secret`, `two_factor_confirmed_at`, `two_factor_recovery_codes`. Enforced per-role via `PlatformSettingsService` keys (`voter_2fa_enforced`, `politician_2fa_enforced`, `citizen_2fa_enforced`). Middleware: `EnsureTwoFactorVerified` (alias `2fa`). Session TTL: `config('platform.standalone.auth.two_factor.session_ttl_minutes')`.
  - **Admin**: columns `admin_two_factor_secret`, `admin_two_factor_confirmed_at`, `admin_two_factor_recovery_codes`. Enforced via the `admin_2fa_enforced` platform setting. Setup/enable/disable/recovery-code rotation live in `AdminController` (dashboard security settings page); the post-login challenge lives in `AdminTwoFactorController`. Middleware: `EnsureAdminTwoFactorVerified` (alias `admin.2fa`). Session TTL: `config('platform.standalone.auth.admin_2fa.session_ttl_minutes')`.
  - Both flows share the TOTP primitives (secret generation, QR rendering, code verification, recovery codes) via the single `App\Services\TwoFactorService` (Phase 2 of the refactor consolidated what used to be two separate service implementations). The column sets and controller entry points remain separate by design — see the Decision Log in `AUTH_REFACTOR_PLAN.md`.
- **Middleware layering** (`routes/standalone.php`): `auth` → `verified` → `check.role` → `no.cache`, then a role-specific inner group adds Spatie `role:{role}` + `check.{role}.onboarding` + the relevant 2FA alias.

## 2. Politician & admin API auth (Sanctum)

`routes/api.php`, `Route::middleware('auth:sanctum')`. Standard Laravel
Sanctum personal-access tokens tied to a `User` record. Admin API routes
additionally require `role:admin` + `admin.2fa`. Used by the dashboard's own
API calls and any first-party integrations acting as a politician or admin.

## 3. Voter widget API auth (bearer token, not Sanctum)

The voter-facing video/ad widget is deliberately **not** Sanctum and **not**
tied to a `User` record — voters who only ever use the widget may have no
`users` row at all.

- **Token model**: `App\Models\VoterApiToken` — an opaque 60-char random
  token, only the SHA-256 hash of which is persisted (`token_hash`). The
  plaintext is returned to the caller exactly once, at issuance or rotation
  (`VoterApiToken::createToken()`), and is never retrievable again.
- **Middleware**: `App\Http\Middleware\AuthenticateVoterToken` (alias
  `voter-token`) resolves the token from the `Authorization: Bearer` header,
  validates the voter is active and the token unexpired, and stores the
  resolved `Voter` + token record on **request attributes** (
  `AuthenticateVoterToken::VOTER_ATTR` / `TOKEN_ATTR`) — not
  `Auth::user()`, since there is no guard for this.
- **Ownership**: `App\Http\Middleware\EnsureVoterTokenMatches` (alias
  `voter.owns:voter|session`) compares the token's resolved voter against
  the route-bound `{voter:uuid}` or `{session:uuid}` resource, so one
  voter's token can't be used against another voter's UUID.
- **Rotation**: `POST /api/v1/voters/token/rotate` (requires the current
  token) issues a new token; the previous one should be treated as revoked
  by the caller.
- **Routes**: grouped under `routes/api.php` `/api/v1/voters/*` and
  `/api/v1/sessions/*`, rate-limited (`throttle:60,1` / `throttle:10,1` for
  registration), and bound by UUID rather than sequential ID to prevent
  enumeration.

## 4. Server-to-server auth (Early-bank integration)

`routes/api.php` under `/api/v1/earlybank/*`, middleware `earlybank.api` +
`throttle:120,1`. Authenticated by a single shared bearer token
(`EARLYBANK_API_TOKEN`) checked in `App\Http\Middleware\EarlyBankApiAuth`.
Expected to be called only from the earlybank.com sibling service, ideally
over a private network. The inbound webhook additionally verifies a
signature inside the controller (rather than the middleware) so a failed
signature returns a structured 401 instead of a hard middleware abort.

## Quick reference

| Consumer | Mechanism | Guard/Model | Where |
|---|---|---|---|
| Dashboard (politician/citizen/voter/admin) | Session cookie | `users` table, `web` guard | `routes/standalone.php` |
| Dashboard API calls (politician/admin) | Sanctum token | `users` table | `routes/api.php`, `auth:sanctum` |
| Voter widget | Opaque bearer token (SHA-256 hashed) | `voter_api_tokens` table, no `User` record required | `routes/api.php`, `voter-token` / `voter.owns` |
| Early-bank service | Shared static bearer token | N/A | `routes/api.php`, `earlybank.api` |
