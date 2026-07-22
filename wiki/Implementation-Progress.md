# Implementation Progress

This page tracks the phase-by-phase implementation status of U9itus and the sprint-level roadmap.

## Phase Tracker

| Phase | Description | Status |
|-------|-------------|--------|
| Phase 1 | Auth & Foundation — auth views, dashboard layout, middleware, email verification | ✅ Complete |
| Phase 2 | Campaign Management — full CRUD, video upload, analytics, billing, profile views; public campaign discovery for guests | ✅ Complete |
| Phase 3 | Analytics & Tracking — ViewSession lifecycle API, fraud detection, payout dispatch | ✅ Complete |
| Phase 4 | Billing scaffold — Stripe service, webhook, credit ledger, billing views | ✅ Complete |
| Phase 5 | Voter watch experience — token-based video delivery, JS heartbeat | ✅ Complete |
| Phase 6 | Admin features — campaign approval queue, edit/stop/reactivate, KYC management, fraud review, immutable audit log | ✅ Complete |
| Phase 7 | Notifications — email on approval/rejection, admin signup email, management system | ✅ Complete |
| Phase 8 | Security & Fraud — advanced scoring, VPN detection, device fingerprinting, bot UA, Tor/datacenter IP blocklist, `fraud_signals` audit | ✅ Complete |
| Phase 9 | Testing — unit tests for all services, feature tests for admin approval, CI coverage | ✅ Complete |
| Phase 10 | Deployment — Railway production config, env hardening | ⬜ Pending |
| Phase 11 | Real-time Notifications — Laravel Reverb/WebSockets (private channels, admin broadcast, ad-delivery push, payout alerts, live presence) | ✅ Complete |
| Phase 12 | Mobile Application — React Native with Metro (Android & iOS, live feed WebRTC, native push notifications, in-app token delivery) | ⬜ Pending |
| Phase 13 | Politician Public Profile Pages — `/p/{slug}` pages with custom themes, layout presets, initiative section, Open Graph meta | ✅ Complete |
| Phase 14 | Repeat Viewing + Campaign Scheduling — repeat-view toggle, delivery windows, `Scheduled` status, Artisan scheduler | ✅ Complete |
| Phase 15 | Voter Benefits & Registration — expanded earnings callout, voter registration questionnaire, registration status field, dashboard prompts | ✅ Complete |
| Phase 16 | Public Records & Transparency — government email verification, opt-in Ballotpedia/OpenSecrets/Vote Smart/FEC data on public profiles | ✅ Complete |
| Phase 17 | User Onboarding System — role-specific multi-phase onboarding flows, progress tracking, skip option | ✅ Complete |
| Phase 18 | In-App Notification System — notification center, real-time bell UI, notification preferences, FCM push, Twilio SMS | ✅ Complete |
| Phase 20 | Native Blog + Civic Events — citizen/politician blog posts, SEO, promoted posts, map pins, Partiful-style events with RSVPs, waitlist, and calendar export | ✅ Complete |

## Sprint Roadmap

### Sprint 0 — Decision Sprint (Week of Mar 16, 2026) `Complete`

- Guest browsing recommendation implemented (directory/profile view-only)
- Existing campaign rates locked to historical values (new campaigns use updated defaults)
- Q&A campaign type naming locked: `q_and_a` with V1 pricing parity
- Stripe fee transparency scope: billing, invoices, and analytics summaries
- Payout threshold canonical key unified to `min_payout_amount`

### Sprint 1 — Pilot-Ready Stabilization (Week of Mar 23, 2026) `Complete`

- Notification API coverage (list, unread count, mark one/all, auth guards)
- Notification bell hydration on politician dashboard UI
- Dynamic settings propagation for campaign pricing and payout paths
- Stripe fee transparency in billing, invoices, and analytics
- `q_and_a` campaign type enabled (enum, validation, forms, migration, tests)
- Logout `419` safeguard for stale CSRF tokens
- Campaign duration guardrails using configurable min/max validation bounds

### Sprint 2 — Pilot Launch + District Foundation (Week of Mar 30, 2026) `Complete`

- District lookup by address
- Guest view-only politician directory browsing
- Guest public profile preview mode
- Homepage public browse entry points
- California unclaimed profile import command (`politicians:import-unclaimed-ca`)
- Deeper auto-population from public source payloads
- Scheduled California sync at 02:00 America/Los_Angeles (`imports:sync-california`)
- Hourly freshness health check (`imports:check-california-health`)
- Admin import monitoring dashboard at `/admin/imports`
- Admin in-app notifications for import success/failure/stale states

### Sprint 3 — Virtual Town Hall: Q&A Videos (Week of Apr 6, 2026) `Complete`

- Topic-based voter browsing
- Intro + Q&A combined profile layout
- Hosted media expansion: YouTube / Vimeo / direct file / S3 / HLS
- Post-view engagement prompt with response capture
- Voter-submitted town hall questions sent to politicians
- Public answered-question profile section
- Admin engagement analytics and question moderation

### Sprint 4 — Transparency Layer (Week of Apr 13, 2026) `Complete`

- Unit test coverage for Dig Deeper normalization, gate conditions, and fail-safes (9 new tests)
- Lightweight provider telemetry for all 4 transparency services with HTTP failure logging and rate-limit detection
- Optional local-candidate context enrichment in Dig Deeper with fail-safe wrapping
- Broadcast driver fix for test suite (`BROADCAST_DRIVER=null` in `phpunit.xml`)
- Remaining: UI readout for `local_candidate_context`; telemetry payload assertion tests

### Sprint 5 — Engagement + Growth Loop (Week of Apr 20, 2026) `Planned`

- Compensated micro-surveys
- Post-view verification prompt / quiz
- Automated approval criteria for scaling moderation
- Homepage / domain refinements
- FAQ / support scaffolding

### Sprint 6 — National Seeding Expansion (Week of Apr 27, 2026) `Planned`

- Generalize California-only seeding into reusable state-parameterized import commands
- Recurring imports for all 50 states with staged rollout controls and idempotent upserts
- Aggregate and per-state operational health checks
- Run-log summaries for state-level created/updated/skipped metrics
- Backward-compatible California aliases during national scheduling transition

## Validation Snapshot

| Check | Status |
|-------|--------|
| Public politician directory guest browsing | ✅ Passing |
| Guest public profile preview mode | ✅ Passing |
| District lookup flow | ✅ Passing |
| Homepage public campaign browse entry path | ✅ Passing |
| Notification API flow | ✅ Passing |
| Notification bell UI hydration | ✅ Passing |
| Candidate matching admin review/import flow | ✅ Passing |
| California import command + health-check simulation | ✅ Passing |
| Campaign CRUD Q&A campaign creation path | ✅ Passing |
| Native blog CRUD, promotion, public index, and RSS | ✅ Passing |
| Civic event CRUD, public browse, RSVP lifecycle, waitlist, and ICS export | ✅ Passing |
| Geo-tagged posts and events on the 3-D map | ✅ Passing |

---

← [Deployment](Deployment.md) | [Home →](Home.md)
