# U9itus Changelog

This changelog is aligned with the public roadmap at:
https://jonathan-head.com/un9itus/

## 2026-03-15 - Roadmap Alignment Update

### Added

- Replaced framework placeholder release notes with a product-level U9itus changelog.
- Added sprint-by-sprint tracking sections matching the public roadmap format.
- Added explicit status markers per sprint item: Complete, In Progress, Planned.

### Notes

- This file now tracks U9itus platform progress, not upstream Laravel framework updates.

---

## Sprint 0 - Decision Sprint (Week of Mar 16, 2026)

Status: Complete

### Decision Log (Locked)

- Existing campaigns policy: keep historical campaign rates unchanged; only new campaigns use updated defaults (Option A).
- Q&A campaign naming: `q_and_a`.
- Q&A payment model: same rate model as intro/video for V1.
- Stripe fee handling scope: transparent fee line-item visibility across billing, invoices, and analytics (Option B).
- Voter payout threshold canonical key: `min_payout_amount`.
- Guest browsing policy: allow directory browsing without login; require login to watch and earn.

### Repository Status

- Guest browsing recommendation is implemented in public directory/profile flows.
- Decision outcomes above are locked and reflected in current implementation scope.

---

## Sprint 1 - Pilot-Ready Stabilization (Week of Mar 23, 2026)

Status: Complete

### Completed in Repository

- Notification API coverage is implemented and passing for list, unread count, mark one, mark all, and auth guards.
- Notification bell hydration wiring is implemented and passing on politician dashboard UI.
- Dynamic amount propagation was implemented for campaign creation, payouts, notifications, and amount-related messaging paths using `PlatformSettingsService`.
- Payout threshold usage was unified to the canonical key `min_payout_amount` in key runtime and UI surfaces.
- Stripe fee transparency is implemented in politician billing, invoices, and analytics summaries.
- `q_and_a` campaign type support is implemented (enum, request validation, forms, migration coverage, and feature test path).
- **Admin logout CSRF safeguard**: Implemented graceful handling of stale/expired CSRF tokens on logout POST requests. Exception handlers in `bootstrap/app.php` now catch `TokenMismatchException` and `HttpException(419)` on logout routes, safely invalidating sessions and redirecting to login instead of surfacing 419 error to administrators.
- **Video duration constraint alignment**: Unified campaign video duration limits to 30–300 seconds across all config, controllers, request validators, and UI form constraints. Updated config defaults (`max_video_duration`, `min_video_duration`), controller fallbacks in `PoliticianController` and `VoterController`, form templates for create/edit/show campaign pages, and added boundary validation test to ensure out-of-range submissions are rejected.

### Closeout Notes

- Sprint 1 stabilization is now complete with all scope items delivered and validated.
- Both critical issues (419 logout error, video duration inconsistency) resolved through centralized exception handling and config-driven validation propagation.
- All code changes merged to master branch and pushed to remote repository.

### Validation Snapshot

- Core regression test suites: 27 tests passed (22 CampaignCrudTest + 5 AuthenticationTest).
- New regression tests added: stale-CSRF logout with 302 redirect validation; campaign media_duration boundary enforcement (30–300s).
- All implementations tested against edge cases (expired tokens, out-of-range durations, fallback behavior).

---

## Sprint 2 - Pilot Launch + District Foundation (Week of Mar 30, 2026)

Status: Partially Complete

### Completed in Repository

- District lookup by address is implemented.
- Unauthenticated browsing of politician directory is implemented (view-only).
- Public politician profile guest preview mode is implemented.
- Home page includes direct campaign browsing entry points for guests.
- Candidate matching review/admin approval and import workflows are implemented with passing feature coverage.
- California unclaimed profile import command is implemented using API-backed congressional data (`politicians:import-unclaimed-ca`).
- Imported California profiles now auto-populate deeper public details from source payloads (city, contact metadata in bio, and seeded video links).

### Remaining Scope

- Run and monitor recurring California profile seeding in production operations.

---

## Sprint 3 - Virtual Town Hall: Q&A Videos (Week of Apr 6, 2026)

Status: Planned

### Planned Scope

- Voter-facing Q&A browsing by topic within district.
- Enhanced profile layout combining intro + Q&A content.
- Hosted media path expansion beyond YouTube links.
- Post-view engagement prompt (simple positive feedback flow).
- Note: `q_and_a` campaign type foundation is already delivered in Sprint 1 enablement work.

---

## Sprint 4 - Transparency Layer (Week of Apr 13, 2026)

Status: In Progress

### Delivered / Partially Delivered

- Transparency controls are present on politician profiles.
- FEC integration is available in current platform configuration.

### Planned Scope

- Expand and harden Ballotpedia, OpenSecrets, and Vote Smart integrations.
- Add a consolidated Dig Deeper profile tab/section for voter research.

---

## Sprint 5 - Engagement + Growth Loop (Week of Apr 20, 2026)

Status: Planned

### Planned Scope

- Compensated voter micro-survey system.
- Post-view engagement verification prompt/quiz.
- Automated user approval criteria for scaling moderation.
- Homepage redesign and custom domain refinements.
- FAQ and support scaffolding.

---

## Backlog (V2+)

Status: Planned

- Dual-role accounts (single identity spanning voter + politician contexts).
- Founding member residual income mechanics.
- Forum/comment system with moderation model.
- System-wide QR code and growth link routing.
- Donation pathway from voter to politician profile.
- Direct messaging between voters and politicians.
- Mobile app strategy exploration.
- Grant strategy tied to survey and pilot outcome data.

---

## Validation Snapshot (Current)

- Public politician directory guest browsing flow is passing.
- Guest public profile preview mode flow is passing.
- District lookup feature flow is passing.
- Home page public campaign browse entry path is passing.
- Notification API feature flow is passing.
- Notification bell UI hydration flow is passing.
- Candidate matching admin review and import flow is passing.
- Campaign CRUD Q&A campaign creation path is passing.
