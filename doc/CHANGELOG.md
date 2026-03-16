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

Status: In Progress

### Decision Log (Target)

- Per-view voter payout recommendation: $0.50/view.
- Per-view politician charge recommendation: $1.00/view.
- Stripe fee handling recommendation: transparent line-item display.
- Voter payout threshold recommendation: $5.00 minimum.
- Guest browsing recommendation: allow directory browsing without login, require login to watch and earn.
- Q&A payment model recommendation: same rate as intro video for V1.

### Repository Status

- Guest browsing recommendation is implemented in public directory/profile flows.
- Remaining items are pending final product decision lock-in and rollout.

---

## Sprint 1 - Pilot-Ready Stabilization (Week of Mar 23, 2026)

Status: Partially Complete

### Completed in Repository

- Notification API coverage is implemented and passing for list, unread count, mark one, mark all, and auth guards.
- Notification bell hydration wiring is implemented and passing on politician dashboard UI.

### Remaining Scope

- Update atomic transaction amounts to Sprint 0 finalized values.
- Add Stripe fee transparency to politician billing UI.
- Enforce $5 payout threshold for voter cashout.
- Resolve 419 logout session issue.
- Enforce 30-second hard max campaign video length.

---

## Sprint 2 - Pilot Launch + District Foundation (Week of Mar 30, 2026)

Status: Partially Complete

### Completed in Repository

- District lookup by address is implemented.
- Unauthenticated browsing of politician directory is implemented (view-only).
- Public politician profile guest preview mode is implemented.
- Home page includes direct campaign browsing entry points for guests.
- Candidate matching review/admin approval and import workflows are implemented with passing feature coverage.

### Remaining Scope

- Seed California politician profiles from API-backed data.
- Auto-populate additional politician profile details from public sources.

---

## Sprint 3 - Virtual Town Hall: Q&A Videos (Week of Apr 6, 2026)

Status: Planned

### Planned Scope

- Q&A campaign type for politician answer videos.
- Voter-facing Q&A browsing by topic within district.
- Enhanced profile layout combining intro + Q&A content.
- Hosted media path expansion beyond YouTube links.
- Post-view engagement prompt (simple positive feedback flow).

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
