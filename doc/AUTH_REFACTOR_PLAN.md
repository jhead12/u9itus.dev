# Authentication Cleanup Plan — U9itus

## Goals

1. Remove dead Laravel Breeze auth code so the active standalone auth system is obvious.
2. Consolidate the two duplicate TOTP implementations into a single shared service.
3. Establish one clear source of truth for user roles (`user_type` column) and simplify role-checking across the app.
4. Split the 1,000-line `AuthController` into focused, single-responsibility controllers.
5. Document the voter API bearer-token auth and fix misleading config/comments.
6. Keep all existing URLs, tests, and behavior green unless explicitly renamed/removed.

## Decision Log

| Decision | Choice | Rationale |
|---|---|---|
| Role source of truth | **`user_type` column** | The entire app already queries `user_type` (scopes, filters, dashboard routing). Spatie roles will be treated as a synced cache/repair layer. |
| Admin 2FA UI | **Keep separate dashboard-style page** | Admin 2FA has a dedicated security settings page inside the dashboard layout. The URL and UX should not change. |
| Voter API auth | **Clean up + document** | The voter widget is intentionally not a `User`/session consumer. We will not migrate to Sanctum now, but we will add clear docs and remove duplication. |

## Phase 1 — Remove dead Breeze code (low risk)

### Files to delete
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `app/Http/Controllers/Auth/ConfirmablePasswordController.php`
- `app/Http/Controllers/Auth/EmailVerificationNotificationController.php`
- `app/Http/Controllers/Auth/EmailVerificationPromptController.php`
- `app/Http/Controllers/Auth/NewPasswordController.php`
- `app/Http/Controllers/Auth/PasswordController.php`
- `app/Http/Controllers/Auth/PasswordResetLinkController.php`
- `app/Http/Controllers/Auth/RegisteredUserController.php`
- `app/Http/Controllers/Auth/VerifyEmailController.php`
- `app/Http/Requests/Auth/LoginRequest.php`
- `resources/views/auth/*` (all Breeze auth views)

### Files to edit
- `routes/auth.php` — delete the file or replace with an empty route file that requires `standalone.php`. Currently it is partly commented out and partly declaring unused routes; this is the source of confusion.
- `config/platform.php` — update `features.two_factor` to `true`, fix the misleading `middleware.standalone.auth` Sanctum comment, and remove the unused `StandardAuthService` mapping.
- `app/Contracts/AuthServiceInterface.php` — delete or move to an ADR note. It is not implemented.

### Validation
- `php artisan route:list` shows no duplicate `login`, `logout`, `register`, `verification.*`, or `password.*` names.
- `tests/Feature/Auth/AuthenticationTest.php` still passes because active routes live in `routes/standalone.php`.
- No references to `AuthenticatedSessionController`, `LoginRequest`, or `AuthServiceInterface` remain.

## Phase 2 — Consolidate TOTP into one shared service

### New file
- `app/Services/TwoFactorService.php` (replace the generic one) — extend it to support:
  - A configurable issuer label prefix (default `"U9itus"`, admin can pass `"U9itus Admin"`).
  - A configurable logo path (`.png` for generic, `.svg` for admin, or a single logo file we standardize on).
  - The existing `generateSecret`, `getOtpAuthUrl`, `renderOtpAuthQrSvg`, `verifyCode`, `generateRecoveryCodes`, `consumeRecoveryCode` methods.

### Files to delete
- `app/Services/AdminTwoFactorService.php`

### Files to edit
- `app/Http/Controllers/Standalone/AdminController.php` — inject `TwoFactorService` instead of `AdminTwoFactorService` in the four 2FA methods.
- `app/Http/Controllers/Standalone/AuthController.php` — use `TwoFactorService` for the admin challenge verify method.
- `tests/Feature/Auth/AdminTwoFactorPolicyTest.php` — swap `AdminTwoFactorService::class` mock references to `TwoFactorService::class`.

### Validation
- Existing tests `AdminTwoFactorPolicyTest` still pass with the shared service.
- No reference to `AdminTwoFactorService` remains in `app` or `tests`.

## Phase 3 — Single source of truth for roles and simpler role middleware

### New files
- `app/Services/UserRoleService.php` — central helper with methods:
  - `resolvePrimaryRole(User $user): string` — returns the first matching role from `['admin','politician','citizen','voter']` based on `user_type`, falling back to `hasRole()`.
  - `hasRole(User $user, string $role): bool` — checks `user_type` first, then Spatie roles.
  - `repairSpatieRole(User $user): void` — assigns the Spatie role that matches `user_type` if missing.

### Files to edit
- `app/Http/Middleware/CheckUserRole.php` — simplify to:
  1. If `user_type` is a known role, ensure the matching Spatie role exists via `UserRoleService::repairSpatieRole()`.
  2. If `user_type` is missing, attempt to infer it from Spatie roles and write it back.
  3. If still unresolved, log out and redirect to `register`.
  Remove the inline `ROLE_ROUTES` redirect after repair; let the normal request continue.
- `app/Models/User.php` — update `isAdmin()`, `isCitizen()`, `scopeAdmins()`, etc. to use `UserRoleService::hasRole()` or a local wrapper.
- `app/Http/Controllers/Standalone/AuthController.php` — replace `roleRedirect()` with a call to `UserRoleService::dashboardRouteFor($user)`.
- `app/Http/Controllers/Standalone/TwoFactorController.php` — use the same helper for `dashboardRoute()`.

### Validation
- `tests/Feature/Auth/AuthenticationTest.php` passes.
- A user with `user_type = voter` but no Spatie role can still log in and reach `voter.dashboard`.
- A user with only Spatie role `voter` but `user_type = null` gets `user_type` repaired and reaches the right dashboard.

## Phase 4 — Split `AuthController` into focused controllers

AuthController currently mixes login, admin login, admin 2FA challenge, registration for three roles, phone OTP, password reset, email verification, and referral handling. Split as follows:

### New controllers

- `app/Http/Controllers/Standalone/LoginController.php`
  - `showLogin`, `login`, `logout`
  - `showAdminLogin`, `adminLogin`
- `app/Http/Controllers/Standalone/RegistrationController.php`
  - `showRegisterChoose`, `showRegisterPolitician`, `registerPolitician`
  - `showRegisterCitizen`, `registerCitizen`
  - `showRegisterVoter`, `registerVoter`
  - `showRegisterClosed`, `storeMailingListSubscriber`
- `app/Http/Controllers/Standalone/PasswordResetController.php`
  - `showForgotPassword`, `sendResetLink`, `showResetPassword`, `resetPassword`
- `app/Http/Controllers/Standalone/PhoneVerificationController.php`
  - `showVerifyPhone`, `verifyPhone`, `resendPhoneCode`
- `app/Http/Controllers/Standalone/EmailVerificationController.php`
  - `showVerifyEmail`, `verifyEmail`, `resendVerification`

### Keep in `AuthController`
- Admin 2FA challenge (`showAdminTwoFactorChallenge`, `verifyAdminTwoFactorChallenge`) and the `roleRedirect`/`dashboardRoute` helpers until Phase 3 moves the redirect helper to `UserRoleService`.
- Or, better: move admin 2FA challenge methods into a new `AdminTwoFactorController.php` so `AuthController` can be deleted entirely.

### Refactor shared helpers
- Move `resolveIncomingReferralCode()` and `markReferralConversion()` into `app/Services/ReferralService.php` or a trait used by registration controllers.
- Move `addToMailgunMailingList()` into `app/Services/MailingListService.php`.

### Files to edit
- `routes/standalone.php` — update imports and route targets.
- `tests/Feature/Auth/AuthenticationTest.php` — no changes needed; only route names matter.

### Validation
- `php artisan route:list` shows all `standalone.php` routes pointing to the new controllers.
- `tests/Feature/Auth/AuthenticationTest.php`, voter registration tests, politician registration tests, and password-reset flows pass.

## Phase 5 — Clean up voter API auth

### Files to edit
- `app/Http/Middleware/AuthenticateVoterToken.php` — add clearer docblock explaining why this is not Sanctum (voters have no User record; tokens are hashed plain tokens scoped to widget API).
- `app/Models/VoterApiToken.php` — add a short class-level note about rotation policy and why the model has no `User` relation.
- `routes/api.php` — add a comment block above the voter-token groups summarizing the auth model.
- Create `docs/auth-architecture.md` (or `AUTH.md` in repo root) with a short section: "Voter widget auth vs. dashboard session auth vs. admin/politician Sanctum API auth."

### Validation
- `tests/Feature/Api/VoterApiTest.php` still passes.
- No behavior changes; only documentation and naming improve.

## Phase 6 — Config and comment hygiene

### Files to edit
- `config/platform.php`:
  - `features.two_factor` → `true` (it is already implemented and enforced per role via settings).
  - Remove or correct the `middleware.standalone.auth` block; the real standalone web routes use `auth` (session), not `auth:sanctum`.
  - Remove the `services.auth.standalone` mapping if `StandardAuthService` does not exist.
- `bootstrap/app.php` — add inline comment above the `'2fa'` and `'admin.2fa'` aliases explaining the split (admin uses dashboard setup page; generic uses standalone setup page).
- `app/Http/Middleware/EnsureTwoFactorVerified.php` — read the TTL from a non-admin config key (`platform.standalone.auth.two_factor.session_ttl_minutes`) instead of `admin_2fa.session_ttl_minutes`.

### Validation
- `php artisan config:cache` succeeds.
- No `StandardAuthService` or `AuthServiceInterface` references remain.

## Phase 7 — Final verification

### Run
```bash
php artisan route:cache
php artisan config:cache
php artisan test --filter=Auth
php artisan test --filter=TwoFactor
php artisan test --filter=Api/Voter
```

### Manual checks
- `/login` renders and submits.
- `/admin/login` renders and submits.
- `/register/politician`, `/register/voter`, `/register/citizen` work.
- `/forgot-password` and reset flow work.
- Admin 2FA setup, challenge, disable, and recovery rotation work.
- Generic 2FA setup and challenge work.
- Voter API token rotation and protected endpoints work.

## Files affected summary

| Area | Files |
|---|---|
| Delete | `app/Http/Controllers/Auth/*`, `app/Http/Requests/Auth/LoginRequest.php`, `app/Services/AdminTwoFactorService.php`, `resources/views/auth/*`, `app/Contracts/AuthServiceInterface.php` |
| Create | `app/Services/UserRoleService.php`, `app/Services/ReferralService.php`, `app/Services/MailingListService.php`, `app/Http/Controllers/Standalone/LoginController.php`, `app/Http/Controllers/Standalone/RegistrationController.php`, `app/Http/Controllers/Standalone/PasswordResetController.php`, `app/Http/Controllers/Standalone/PhoneVerificationController.php`, `app/Http/Controllers/Standalone/EmailVerificationController.php`, `app/Http/Controllers/Standalone/AdminTwoFactorController.php`, `docs/auth-architecture.md` |
| Edit heavily | `routes/standalone.php`, `app/Http/Controllers/Standalone/AuthController.php` (then delete), `app/Http/Controllers/Standalone/AdminController.php`, `app/Http/Controllers/Standalone/TwoFactorController.php`, `app/Http/Middleware/CheckUserRole.php`, `app/Models/User.php`, `config/platform.php`, `tests/Feature/Auth/AdminTwoFactorPolicyTest.php` |
| Edit lightly | `bootstrap/app.php`, `app/Http/Middleware/EnsureTwoFactorVerified.php`, `app/Http/Middleware/AuthenticateVoterToken.php`, `app/Models/VoterApiToken.php`, `routes/api.php`, `routes/auth.php` |

## Rollback notes

- All route names remain unchanged, so external links/bookmarks continue to work.
- No database migrations are required in this plan.
- If admin 2FA shared service causes issues, the original `AdminTwoFactorService` can be restored from git.
- If role refactor causes issues, `CheckUserRole` can be reverted to its current version.
