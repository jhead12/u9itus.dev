# Super Admin and delegated staff permissions — implementation handoff

Last inspected: 2026-09-22. This document is the durable build specification and restart prompt. Checkpoints 1-4 have a working implementation in the tree (uncommitted, on `codex/politician-chatter-review`) — see the progress ledger below for status and verification evidence before assuming anything is unfinished.

## Copy-and-paste resume prompt

```text
Implement the Super Admin and delegated staff permission system described in
doc/SUPER_ADMIN_PERMISSIONS_HANDOFF.md. Read the entire document and applicable
AGENTS.md instructions first. Inspect Git status, branch, commits, and the actual
implementation before changing anything. Preserve unrelated work.

Continue from the first unfinished checkpoint, verifying completed work rather
than repeating it. Use the existing Laravel/Spatie architecture. Enforce access
on server routes, controller/service actions, exports, APIs, and dashboard data,
as well as navigation. Social/blog staff must not receive financial or video
access unless explicitly granted. Keep existing authentication and 2FA intact.

Record progress, exact test results, decisions, blockers, and the next action in
this document after each meaningful checkpoint. Commit coherent milestones on
a codex/ branch, recording their hashes here when possible. Do not deploy,
change production accounts, send invitations, or connect external accounts
without authorization. Complete implementation and relevant verification;
report any remaining deployment or owner-selection requirements explicitly.
```

## Objective and scope

Allow a Super Admin to create named staff roles from a fixed permission catalog,
assign those roles to users, and revoke access. A social media or blog manager
can use their assigned tools without gaining access to financial information,
campaign videos, user administration, or global settings.

Interpretation to preserve: the user's phrase “but can access the financial or
the video platform” was interpreted in the discussion as “cannot access” unless
specifically granted. The implementation should use that least-privilege default.

Included: permission catalog, protected Super Admin role, custom staff roles,
staff assignment UI, backend enforcement, role-aware dashboard/navigation,
permission-change auditing, existing-admin migration, recovery and rollout docs.

Excluded: social OAuth/API integration, automatic post retrieval, public source
submissions, AI classification, and changes to money-processing business logic.
Those are separate features; expose connection-management permissions only when
the corresponding functionality exists. Do not present unavailable integrations
as working capabilities.

## Verified starting state

- Inspected branch: `codex/politician-chatter-review`.
- Existing feature commit: `13fa5d05 Add reviewed politician chatter system`.
- That feature includes manual source intake, admin moderation, public profile
  cards, and moderation logs. No social account connection or automated collection.
- `app/Models/User.php` uses Spatie `HasRoles` and a separate `user_type` field.
- `config/permission.php` configures Spatie roles/permissions.
- `bootstrap/app.php` registers `role`, `permission`, and `role_or_permission` middleware.
- `database/seeders/RoleSeeder.php` creates only a small legacy permission set and
  calls `syncPermissions` on built-in roles. Unchanged, rerunning it could erase
  new assignments on those roles.
- `app/Services/UserRoleService.php` treats `user_type` as canonical and repairs
  the matching Spatie role. Its built-in portal roles are admin/politician/citizen/voter.
- `app/Console/Commands/CreateAdminUser.php` calls `syncRoles(['admin'])` and sends
  account email. Review this path so it does not erase staff roles or silently
  grant privileged access. Do not run it against real accounts during tests.
- `routes/standalone.php` protects the admin group with `role:admin`, onboarding,
  and `admin.2fa`, inside authenticated/verified/no-cache middleware.
- `routes/api.php` also has an admin group with role and 2FA checks.
- `app/Http/Middleware/CheckAdminOnboarding.php` and
  `app/Http/Middleware/EnsureAdminTwoFactorVerified.php` rely on current admin behavior.
- Admin navigation is in `resources/views/standalone/layouts/dashboard.blade.php`.
- Chatter controller: `app/Http/Controllers/Standalone/AdminPoliticianChatterController.php`.
- Most admin operations live in `app/Http/Controllers/Standalone/AdminController.php`;
  also inspect dedicated admin controllers and `app/Http/Controllers/Api/AdminController.php`.

These observations are a baseline, not a substitute for checking current files.
No Super Admin implementation has been completed at the time of this handoff.

## Architectural decisions

1. Reuse Spatie; do not create a competing authorization database.
2. Keep `user_type=admin` and the base `admin` role as portal membership where
   compatible with existing login, onboarding, and 2FA. The base role must not
   implicitly confer every operational permission once rollout is complete.
3. Add a protected `super_admin` role alongside base portal membership. Use a
   consistent Laravel Gate/policy authorization path for its permission bypass;
   verify that selected middleware honors it. Direct `hasPermissionTo` calls do
   not necessarily use a Gate bypass. Do not allow unknown permission names to
   become an accidental bypass for ordinary staff.
4. Permissions are stable code-defined capabilities; admins create role names
   and choose capabilities, not arbitrary strings that promise nonexistent access.
5. Prefer role-based assignments for v1. Avoid per-user overrides unless needed;
   if added, show effective permissions and define revocation behavior explicitly.
6. Protect role administration itself with a Super Admin-only policy. Generic
   user editing, imports, registration, APIs, and mass assignment must not alter
   privileged roles or self-promote users.
7. New staff receive no operational access by default. Provide a minimal portal
   home with no sensitive totals and a clear empty-access message.
8. Existing ordinary admin access may be preserved through a one-time explicit
   legacy full-access role. Do not automatically promote every admin to Super
   Admin. Identify the initial owner explicitly before production activation.
9. Keep permission changes transactional and auditable. Revoke access promptly
   in current sessions and clear the appropriate permission caches after changes.
10. Prevent removal, demotion, suspension, or deletion of the last active Super
    Admin. Handle concurrent requests with a consistent lock/transaction strategy,
    including existing user-management paths that can disable that account.

## Proposed permission catalog

Finalize names after inventorying routes. Treat this as a proposed contract.

| Area | Suggested capabilities |
| --- | --- |
| Chatter | `chatter.view`, `chatter.create`, `chatter.edit`, `chatter.publish`, `chatter.moderate` |
| Blog | `blog.view`, `blog.create`, `blog.edit`, `blog.publish`, `blog.archive`, `blog.delete` |
| Campaigns/video | `campaigns.view`, `campaigns.edit`, `campaigns.approve`, `campaigns.manage`, `campaigns.audit.view` |
| Finances | `finance.reports.view`, `finance.export`, `finance.payouts.manage`, `finance.refunds.manage` |
| Users | `users.view`, `users.suspend`, `users.delete`, `users.restore` |
| Sensitive review | `kyc.review`, `kyc.documents.view`, `fraud.view`, `fraud.manage` |
| Civic data/content | `civic.view`, `civic.edit`, `civic.import`, `data_reports.review` |
| Settings | `settings.view`, `settings.manage`, `email_templates.manage` |
| Staff administration | Super Admin-only role and staff assignment operations |

Do not use one broad `reports.view` permission for both content analytics and
financial ledgers. Make shared screens safe for their permitted audience or split
them. Distinguish viewing a KYC status from downloading identity documents.
Keep self-service account/password/2FA settings accessible without global settings access.

Suggested starter roles: Social Reviewer, Social Publisher, Blog Editor, Blog
Publisher, Campaign Reviewer, Finance Viewer, Finance Operator. Each template
must show its exact grants; names alone never authorize requests. Creating a
blog role must not silently promise a creation UI absent from existing code.
Inspect existing post ownership and editing flows, then implement needed staff
authoring support or explicitly document an agreed scope limit.

## Implementation checkpoints

### 1. Inventory and access matrix

- Inspect all admin web/API routes, controllers, download/export endpoints,
  dashboard queries, notifications, login redirects, role repair, onboarding,
  registration, existing seeds, and admin creation commands.
- Produce a route/action-to-permission matrix in this document or a linked file.
  Include bulk operations, alternate HTTP verbs, and sensitive indirect reads.
  **Implemented as `config/admin_routes.php`** (163 route-name → permission
  entries, `@staff`/`@owner`/`@action` sentinels for special cases) plus
  `config/admin_route_requirements.php` for routes needing more than one
  permission. `tests/Feature/Standalone/StaffPermissionsTest.php` asserts every
  `admin.*` and `api.v1.admin.*` route name has an entry and that any route
  absent from the file is denied by default — treat that test, not a prose
  table, as the matrix's source of truth.
- Map current roles and schema using local/test data. Never expose account
  secrets or change production users to prepare the implementation.
- Confirm staff can complete onboarding without being sent to forbidden finance
  or campaign screens. Adjust onboarding for delegated staff as necessary.

### 2. Catalog, ownership, and migration

- Implement catalog and idempotent installation/backfill with explicit guard names.
- Fix legacy seeding and role repair so they preserve intended custom assignments.
- Implement explicit owner bootstrap and a documented recovery command. Separate
  role promotion from account creation and email sending.
- Document migration order: install catalog, identify owner, preserve legacy
  operational access, then enable enforcement. Avoid any interval where base
  membership grants new staff full access.
- Protect reserved role names and last-owner invariants.
- Test repeated installation and recovery with isolated fixtures.

### 3. Authorization enforcement

- Apply permissions to every matrix entry; default-deny unclassified admin
  operational routes. Explicitly allow account and minimal landing routes.
- Put checks before data fetching or side effects. Include APIs, exports, document
  downloads, refunds/payouts, role mutations, and bulk actions.
- Keep business-level authorization/ownership checks as well as staff permissions.
- Gate dashboard data at the query/serialization layer. Hiding a Blade card does
  not prevent a controller from exposing the same values elsewhere.
- Ensure mixed-role accounts and the existing portal picker behave correctly.

### 4. Super Admin and delegated staff UI

- Role list, create/edit form with grouped permission checkboxes and descriptions,
  staff search/assignment/revocation, and effective access summary.
- Keep privileged roles protected from arbitrary rename/delete/edit.
- Audit changes with actor, target, timestamp, before/after grants, and outcome.
  Avoid putting credentials or tokens in audit payloads.
- Make navigation and buttons reflect backend checks, including partial access
  within a module. Choose an accessible landing page for each staff member.
- Verify desktop/mobile layouts and empty/error states in a browser when possible.

### 5. Verification and rollout handoff

- Run focused authorization tests, related authentication/2FA tests, template
  compilation, and route checks. Use existing test DB configuration; do not run
  destructive resets or migrations against a shared/production database.
- Record exact commands/results and unresolved failures below. Expand testing
  when shared role/auth behavior changes, rather than claiming all tests passed
  after only a narrow feature test.
- Document deployment/backfill order, initial owner selection, cache reset,
  worker restart if relevant, monitoring, and recovery. A rollback must not
  silently reopen unrestricted admin access.
- Commit completed changes. Push/PR/deployment only as authorized by the user.

## Acceptance tests

- Guests and nonstaff cannot reach staff endpoints.
- A social-only staff member can use permitted chatter actions but receives 403
  for finance/video/blog/user/settings routes, including direct requests and APIs.
- An editor can save drafts but cannot publish by forging an action parameter.
- Finance viewers cannot refund, change payouts, or invoke exports without grants.
- Users with multiple staff roles receive the intended union of permissions.
- Revoking a role blocks the next protected request from an existing session.
- Sensitive values are absent from restricted dashboard HTML, JSON, and exports.
- Ordinary staff cannot assign roles, self-promote, mutate protected role names,
  or gain privileges through generic profile/user update endpoints.
- Super Admin has full intended operational access while still requiring login,
  verification, active-account checks, and existing 2FA policy.
- Last-owner protection covers demotion, suspension, deletion, and concurrent actions.
- Repeated seed/backfill execution does not erase custom roles or duplicate grants.
- Admin login, onboarding, account settings, role repair, and mixed-role navigation
  still work for existing users and new restricted staff.
- Audit entries and state changes succeed or roll back together.

## Interruption and recovery protocol

After each checkpoint, update the ledger below with completed files, commit
hashes, verification evidence, unresolved issues, and the next concrete action.
Record dirty files if a checkpoint cannot be committed. Never store tokens,
passwords, recovery codes, full environment contents, or customer data here.

On restart: read this document, inspect `git status --short --branch` and recent
commits, compare the ledger to the code, check applicable instructions, and resume
the first incomplete step. Do not assume that a previous agent's “done” statement
means a feature was verified or deployed. Do not recreate a branch if the work
already exists. Commit hashes listed here are navigation aids, not reset targets.

## Progress ledger

| Checkpoint | Status | Evidence / next action |
| --- | --- | --- |
| Documentation | Complete | This build specification |
| Existing chatter | Committed | `13fa5d05`; manual intake/moderation only |
| 1. Inventory | Done | `config/admin_routes.php` (163 entries) + `config/admin_route_requirements.php` act as the matrix; coverage and default-deny both asserted by `StaffPermissionsTest` |
| 2. Catalog/migration | Done | `AdminPermissionInstaller` (idempotent, preserves edited starter roles — test-covered), `database/migrations/2026_09_22_000002_install_admin_permissions.php` grandfathers pre-existing admins into a protected `staff:Legacy administrator` role with the full catalog (not auto-`super_admin`, per decision 8), `RoleSeeder` no longer syncs privileged permissions onto `admin`, `CreateAdminUser` changed from destructive `syncRoles(['admin'])` to additive `assignRole('admin')` so it can no longer erase staff/owner roles |
| 3. Enforcement | Done | `AuthorizeAdminAccess` middleware registered globally on both `web` and `api` groups (`bootstrap/app.php`), path-filtered to `admin*` / `api/v1/admin*`, default-denies any route name absent from `admin_routes.php`; dashboard totals gated in `AdminController::dashboard()` before the query runs, not just hidden in Blade |
| 4. UI | Done (not browser-verified) | Role CRUD, staff search/assign/revoke, effective-access summary, and audit log view all exist (`AdminStaffController`, `staff-access.blade.php`, `StaffAccessService`); sidebar nav in `dashboard.blade.php` gated per-link through `AdminAccess::canRoute()`. Still needs a real desktop/mobile pass in a browser per checkpoint 4's own instruction. |
| 5. Verification/rollout | Tests done, rollout doc not written | `StaffPermissionsTest`: 9/9 passed, 212 assertions. Full `Standalone/Admin/Api/Campaign/Citizen/Payout` suite: 623 passed, 1 pre-existing unrelated failure (`MapStateCandidatesDiscoveryGateTest`, a seeding constraint issue, not touched by this feature). `route:list --path=admin`: 160 routes resolve cleanly. Deployment/backfill-order/cache-reset runbook text still needs writing below before production rollout. |

Initial Super Admin production account: **still not selected.** This is the one
remaining item that blocks production activation — it is independent of code
completeness above, and nothing in this implementation picks an owner for you.
`php artisan admin:bootstrap-owner` exists for this but must not be run against
a real account without explicit authorization.

Known follow-up (not yet resolved as of this entry): the `admin.monitor`
broadcast channel (`routes/channels.php`) was briefly owner-only during
development, which would have cut grandfathered legacy admins off from the
real-time fraud/analytics stream despite decision 8's "preserve existing
access." It now checks `AdminAccess::allowed($user, 'fraud.view')`, which
owners and legacy admins (and any staff role granted that permission) satisfy.
Re-verify this is still true if the channel is touched again.

### Next-agent checkpoint entry template

```text
Date / agent:
Branch / latest commit:
Checkpoint completed:
Files changed / dirty files:
Decisions and rationale:
Commands run and exact results:
Known failures / blockers:
Next concrete action:
Production changes performed (normally none):
```
