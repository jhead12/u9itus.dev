# U9itus × Web3 Creative Integration Plan

Reference: patterns from `creativeplatform/crtv3` (MeTokens, Livepeer, Story Protocol, 0xSplits, Songchain/Lens, Account Kit, XMTP, x402).

---

## Status Summary

| Phase | Focus | Status |
|---|---|---|
| **Phase 19** | Profile Badges + Voter Favorites | ✅ **Completed** (2026-06-30) |
| Sprint 7 | MeToken subgraph read-only enrichment (governor profiles) | ⏳ Not started |
| **Sprint 7.5** | Citizen role foundation | ✅ **Completed** (2026-07-01) |
| Sprint 8 | Livepeer as selectable `media_type` | ⏳ Not started |
| Sprint 8.5 | Neighborhood Groups schema + admin UI | ⏳ Not started |
| Sprint 9 | MeToken opt-in — governor candidates (Sovereign tier) | ⏳ Not started (blocked on legal review) |
| Sprint 9.5 | Neighborhood Token opt-in | ⏳ Not started |
| Sprint 10 | Voter smart wallet provisioning | ⏳ Not started |
| Sprint 11 | Story Protocol IP registration | ⏳ Not started |
| Sprint 12 | 0xSplits payout distribution | ⏳ Not started |
| Sprint 13 | Songchain music playlists | ⏳ Not started |

---

## ✅ Phase 19 — Profile Badges + Voter Favorites (Completed)

Implemented test-first (TDD). Merged to `master`.

### What shipped

- **Badge catalog** — extended existing `politician_topics` table with `badge_icon_url`, `badge_color`, `voter_selectable`, `auto_earned` columns, so curated topics double as the badge catalog.
- **`profile_badges`** — polymorphic table (`badgeable_type`/`badgeable_id`) supporting Politicians and Voters (Citizen-ready), with `badge_type` (`self_declared`, `earned_views`, `earned_referral`, `endorsed`), unique per `[badgeable, topic]`.
- **`voter_favorite_politicians`** — voter favorites join table, unique per `[voter_id, politician_id]`.
- **`HasProfileBadges` trait** — shared behavior (`badges()`, `publicBadges()`, `addBadge()`) used by `Politician` and `Voter` models.
- **`BadgeService`** — validation/orchestration for add/remove, respecting `voter_selectable`/`auto_earned` topic flags.
- **`BadgeController`** and **`FavoriteController`** — HTTP endpoints for badge CRUD and favorite/unfavorite, added to `routes/standalone.php` under both `politician.` and `voter.` route groups.
- **Auto-earn hook** — `PoliticalViewService::completeView()` grants an `earned_views` badge automatically once a voter completes 5+ views on campaigns tagged with a topic.
- **`Politician` model** — added `favoritedByVoters(): BelongsToMany` and `isEligibleForMeToken(): bool` (gated to `governance_level === 'state'` + `political_office` = Governor, in prep for Sprint 9 scope).
- **`Voter` model** — added `favoritePoliticians(): BelongsToMany`.
- **Voter dashboard view** — `resources/views/standalone/voter/favorites/index.blade.php` ("Politicians I Follow").

### Files created

- `database/migrations/2026_07_01_000001_add_badge_fields_to_politician_topics.php`
- `database/migrations/2026_07_01_000002_create_profile_badges_table.php`
- `database/migrations/2026_07_01_000003_create_voter_favorite_politicians_table.php`
- `app/Models/ProfileBadge.php`
- `app/Traits/HasProfileBadges.php`
- `app/Services/BadgeService.php`
- `app/Http/Controllers/Standalone/BadgeController.php`
- `app/Http/Controllers/Standalone/FavoriteController.php`
- `resources/views/standalone/voter/favorites/index.blade.php`
- `tests/Unit/Traits/HasProfileBadgesTest.php` (11 tests)
- `tests/Feature/Badges/BadgeManagementTest.php` (9 tests)
- `tests/Feature/Badges/FavoritePoliticianTest.php` (9 tests)

### Files modified

- `app/Models/Politician.php`, `app/Models/Voter.php`, `app/Models/PoliticianTopic.php`
- `app/Services/PoliticalViewService.php`
- `routes/standalone.php`

### Test results

- 29/29 new tests passing (51 assertions)
- Full regression suite: 430/430 tests passing (1308 assertions) — zero regressions
- Merged: feature branch `u9itusXcreativ` → `master` (commit `53a83dae`)

### Remaining follow-up (not yet done)

- Badge chip UI partial rendered on the public politician profile page (`/p/{slug}`) — data layer and tests exist, visual rendering not yet wired in.

---

## Part 1 — Cost Structure: What Web3 Actually Adds

Honest per-view cost math based on the crtv3 patterns reviewed.

### Fixed one-time setup costs (per politician account)

| Item | Cost | Who pays it | Notes |
|---|---|---|---|
| **MeToken deployment (Base L2)** | ~$0.05 gas + $10–$100 DAI collateral | Politician (self-funded) | Collateral stays as their backing; they can withdraw later |
| **Story Protocol IP registration** | ~$0.02 gas per campaign video | Politician (per video) | On Story Protocol Aeneid/Mainnet |
| **Smart account (Account Kit) creation** | $0 for user (Alchemy Paymaster sponsorship) | Platform | Alchemy free tier: 300M CU/mo — covers ~500k voter wallets |

### Recurring per-view costs (Web3-enabled campaigns)

| Item | Cost per view | Baseline (Web2) | Delta |
|---|---|---|---|
| **YouTube playback** | ~$0.00 | $1.00 charge | 0 |
| **Livepeer transcoded playback** (30-sec HD, 5MB payload) | ~$0.002 | — | +$0.002 |
| **On-chain view attestation** (optional batch write) | ~$0.0001 (batched hourly) | — | +$0.0001 |
| **0xSplits distribution gas** (batched weekly for 100 voters) | ~$0.001 amortized | — | +$0.001 |

Total added ops cost per view for full-stack Web3: **~$0.003** — comfortably absorbed by the existing $0.18–$0.30 margin.

### Proposed Pricing Tiers (using existing `PlatformSetting.user_tier` field)

`PlatformSetting` already supports `user_tier` scoping with `effective_from/until` — no schema changes needed. Three canonical tier values:

```
'user_tier' column values:
  - 'standard'      → $1.00/view, YouTube/S3 only    (default, existing behavior)
  - 'verified_plus' → $1.05/view, adds Livepeer + Story Protocol per-video registration
  - 'sovereign'     → $1.10/view, adds MeToken economy + on-chain payouts
```

One-time **setup fee row** for the meToken tier: `platform_setting.key = 'metoken_setup_fee'`, `value = 25.00`, category = `web3`. Covers gas for DAI approval + factory call + admin overhead.

**Design principle:** the politician chooses their tier at campaign creation, not account creation — the same politician can run a cheap YouTube outreach campaign and a premium on-chain campaign side-by-side.

---

## Part 2 — Is the Video Layer Selectable?

Yes — `PoliticalCampaign` already has [media_type](../app/Models/PoliticalCampaign.php#L37) as a fillable enum column. Adding `livepeer` is a single ENUM migration matching the pattern in [hls_stream migration](../database/migrations/2026_04_02_000004_add_hls_stream_media_type_to_campaigns.php).

The politician selects `media_type` in the campaign create form; picking `livepeer` means consenting to the +$0.05/view surcharge. The voter never sees a difference.

Voters never *choose* Web3 either — they silently get a wallet address (via Account Kit) tied to their U9itus account. If they engage with a Web3-enabled campaign, they earn meTokens on top of USD. Otherwise, everything works as-is.

---

## Part 3 — Implementation Plan (Concrete Sprints)

### Sprint 7 — Web3 Foundation (Read-Only, Zero Risk)

**Goal:** Show on-chain meToken stats on politician profiles. No writes, no wallet, no user-facing crypto UX.

- `app/Services/Web3/MeTokenSubgraphService.php` — mirrors `BallotpediaService` pattern (24-hour cache, HTTP client, graceful fail-safe). Queries the public Goldsky endpoint `https://api.goldsky.com/api/public/project_cmh0iv6s500dbw2p22vsxcfo6/subgraphs/metokens/1.0.2/gn`. Method: `getMeTokenByOwner(string $walletAddress): ?array`.
- Migration: `politicians.wallet_address` (nullable, indexed) + `politicians.metoken_address` (nullable).
- View partial: `resources/views/standalone/public/partials/metoken-stats.blade.php` in the Dig Deeper section on `/p/{slug}`.
- Toggle in `platform_settings`: `web3_features_enabled` (default false).

**Cost to platform:** $0. **Risk:** minimal — pure enrichment on the existing transparency layer.

### ✅ Sprint 7.5 — Citizen Role Foundation (Completed 2026-07-01)

**Branch:** `feature/citizen-role` — merged to `master` (commit `eae359e5`). **475/475 tests passing.**

#### What shipped

- **`citizens` table + `Citizen` model** — UUID, slug, referral code (`C` prefix), `user_id` FK, address fields, `neighborhood_group_id` (nullable placeholder for Sprint 8.5), `verified_at`, `stripe_verified_at`.
- **`citizen_campaigns` table + `CitizenCampaign` model** — parallel to `political_campaigns` (separate for compliance/audit), plus `citizen_ad_type`, `target_zip`, `target_zip_radius`, `pac_registration_id`, `daily_view_cap` (500 standard, uncapped for ballot issue). Boot hook applies tier-scoped pricing via `PlatformSettingsService`.
- **`CitizenAdType` enum** — `local_business`, `community_notice`, `ballot_issue`, `general_announcement`.
- **Tiered pricing via `CitizenTierPricingSeeder`** — `citizen_revenue_per_view` = $0.75, `ballot_issue_revenue_per_view` = $1.00 (namespaced keys; `platform_settings.key` has a standalone unique index).
- **Registration flow** — `/register/citizen` (address + ad-type selection), `CheckCitizenOnboarding` middleware, `citizen` Spatie role, onboarding bypass (phases empty for 7.5).
- **Full campaign CRUD** — `CitizenController`: create, store, show, edit, update, destroy, submitForReview + full S3 upload pipeline (`uploadVideo`, `getS3UploadUrl`, `processS3UploadedVideo`) reusing shared `HandlesCampaignVideoUpload` trait (also deduped from `PoliticianController` and `AdminController`).
- **Form requests** — `CreateCitizenCampaignRequest` (tier-aware pricing, `q_and_a` blocked, PAC ID conditional on ballot issue) + `UpdateCitizenCampaignRequest`.
- **Blade views** — `citizen/campaigns/{index,create,show,edit}` + updated dashboard with campaign count + CTA.
- **Admin ballot-issue approval queue** — `AdminController::pendingCampaigns` now unions both political and citizen pending campaigns; `approveCitizenCampaign` / `rejectCitizenCampaign` with separate tab in `campaigns-pending.blade.php`.
- **Tests** — 26 new tests (`CitizenCampaignCrudTest`, `CitizenCampaignModerationTest`, `CitizenCampaignModelTest`).

#### Deliberately deferred

- Voter watch/earn from citizen campaigns — requires `view_sessions` polymorphism (Phase E).
- `citizen_credits` ledger + Stripe billing for citizens (Phase F).
- `CampaignAuditLog` polymorphism — FK currently targets `political_campaigns` only.
- Mail/notification/broadcast wiring for citizen approval events.
- Auto-approval for verified non-ballot citizens (Phase F, after Stripe Identity).
- Public `/c/{slug}` citizen profile directory (Phase G).
- `neighborhood_groups` table (Sprint 8.5).

---

### Sprint 7.5 — Citizen Role Foundation (original spec)

New user segment: pays to distribute content at smaller scale, without FEC/election-commission burden.

| Role | Pays or Earns? | Content Type | Web3 Eligible? |
|---|---|---|---|
| Admin | — | Moderation | — |
| Politician (federal/state/municipal) | Pays | Political campaigns | Verified+ for all; Sovereign for governors only |
| **Citizen** (new) | Pays | Local ads: business, community, ballot issues | Verified+ standard; MeToken opt-in via community threshold |
| Voter (all viewers) | Earns | — | All voters silently get smart wallets |

**Why Citizen ≠ Politician:** Politicians are subject to FEC/state election law (`political_office`, `governance_level`, `party`, district targeting exist for compliance). Citizens are commercial/civic actors needing locality binding (`target_zip_radius`/`target_city`), a `citizen_ad_type` enum (`local_business | community_notice | ballot_issue | general_announcement`), and simpler verification (email + address, no `.gov`/`.mil`).

**Citizen pricing vs. Politician (Standard tier):**

| Component | Politician | Citizen |
|---|---|---|
| Cost per view | $1.00 | $0.75 |
| Voter payout | $0.50 | $0.50 |
| Referral commission (10%) | $0.05 | $0.05 |
| Platform net | ~$0.45 | ~$0.20 |
| Reach cap | Unlimited (subject to targeting) | 500-view daily cap per campaign |
| Min campaign spend | $50 | $10 |

**Web3 placement for Citizens:**

| Citizen Tier | Cost | Web3 Layer |
|---|---|---|
| Standard | $0.75/view | YouTube/S3, PayPal payouts, no wallet |
| Verified+ Local | $0.80/view | Livepeer hosting + Story Protocol IP registration |
| Neighborhood Token (opt-in, communal) | $0.85/view + $10 one-time | MeToken issued to a `neighborhood_group`, not an individual — multiple Citizens co-mint under one shared token |

**Ballot issue Citizens (special case):** require `pac_registration_id`, route through admin approval queue like politician campaigns, same $1.00/view pricing, eligible for the on-chain transparency layer.

**Schema additions:**

```
citizens                     # new — mirrors politicians but simpler
  id, uuid, slug, user_id
  business_name (nullable)
  citizen_ad_type            # local_business|community_notice|ballot_issue|general
  verified_address_id        # FK to address verification table
  pac_registration_id        # nullable, required if ad_type = ballot_issue
  neighborhood_group_id      # nullable, FK for shared MeToken groups

neighborhood_groups          # new — communal MeToken container
  id, uuid, name, city, state
  metoken_address            # on-chain MeToken for the group
  admin_citizen_id           # who manages it

citizen_campaigns            # new table — parallel to political_campaigns
  # Same shape as political_campaigns, minus governance_level/district
  # Plus: target_zip, target_zip_radius, citizen_ad_type
```

Recommendation: keep `citizen_campaigns` **separate** from `political_campaigns` (cleaner audit trails, distinct moderation queues, easier to remove without breaking the other) — share `view_sessions` and payout infrastructure downstream.

New Spatie role: `citizen`. New registration route: `/register/citizen` (address + ad-type selection). Voter experience is unaffected — the watch flow, payout, referral commission, and fraud-prevention pipeline work identically regardless of ad source.

### Sprint 8 — Livepeer as Selectable Media Type

- Migration: extend `media_type` enum to include `livepeer`.
- `app/Services/Video/LivepeerService.php` — upload API wrapper (`POST /asset/request-upload`, poll for `playbackId`).
- Update campaign create view with a Livepeer option ("+$0.05/view — censorship-resistant hosting").
- Update watch view player switch for `media_type === 'livepeer'` (Livepeer's `@livepeer/react` player or HLS.js).
- `PoliticalPaymentService::chargePerView()` — read tier from campaign, apply surcharge.

### Sprint 8.5 — Neighborhood Groups Schema + Admin UI

Group creation, member invites, shared campaign attribution for the Citizen Neighborhood Token model.

### Sprint 9 — MeToken Opt-In for Politicians (Governor tier only)

- `app/Services/Web3/MeTokenSetupService.php` — orchestrates one-time $25 setup fee via Stripe (existing `CampaignBillingService::createPurchaseIntent` pattern) → dispatches `DeployMeTokenJob` on success.
- `app/Jobs/DeployMeTokenJob.php` — calls `METOKEN_FACTORY.create` on Base, updates `politicians.metoken_address`.
- Route: `POST /politician/web3/enable-metoken`.
- Onboarding: optional phase "Enable On-Chain Loyalty Token" via existing `OnboardingService` phase machinery.
- Gated by `Politician::isEligibleForMeToken()` (already implemented in Phase 19).

### Sprint 9.5 — Neighborhood Token Opt-In

Group-level MeToken deployment via `MeTokenSetupService`, unlocked at a 3+ member threshold.

### Sprint 10 — Voter Web3 (Silent Onboarding)

- `app/Services/Web3/SmartAccountService.php` — Alchemy Account Kit REST API provisions a smart wallet at voter registration. Store address in `voters.smart_account_address`.
- Alchemy Paymaster policy sponsors voter gas up to N txs/month.
- Voter dashboard: "On-Chain Earnings" card.

### Sprint 11 — Story Protocol IP Registration (Per Campaign)

- `app/Services/Web3/StoryProtocolService.php` — mirrors crtv3's `services/story-protocol.ts`. Called on publish success for `media_type = livepeer` campaigns (politicians and Citizens with Verified+ tier).
- Adds `political_campaigns.story_ip_id`, `story_ip_registered_at`, `story_license_terms_id`.
- "🔗 Registered as IP" badge on the campaign card.

### Sprint 12 — On-Chain Payout Layer (0xSplits)

- `app/Services/Web3/SplitsPayoutService.php` — batches voter payouts into a split contract per weekly cycle, alongside existing `PayPalPayoutService`.
- `voters.payout_preference` enum: `paypal | cashapp | onchain_usdc | metoken`.

### Sprint 13 — Music Layer (Songchain-style)

- `app/Services/Web3/LensFeedService.php` — reads Lens/Orb feeds for a politician's connected Lens account.
- `politicians.lens_account_id` column.
- "Campaign Playlist" section on `/p/{slug}` — meToken holders unlock full curated playlist. Available to politicians and neighborhood groups.

---

## Part 4 — Governance & Rollout

### Feature flag protection

```
key: web3_features_enabled
value: false
category: web3
description: Master kill switch for all Web3 integrations
```

Every Web3 code path checks `PlatformSettingsService::get('web3_features_enabled', null, false)` before executing — flipping it off reverts the platform to pure Web2 mode instantly.

### Testing pattern

Mirror the existing service test pattern (`BallotpediaService` → `BallotpediaServiceTest`). Each new Web3 service gets:

- HTTP fake for the RPC/subgraph endpoint (`Http::fake()`)
- Cache assertion (24-hour TTL)
- Graceful failure test (returns null, not thrown exception)
- Rate-limit / 429 test

### Regulatory note

A politician-branded meToken with monetary value earned by watching political ads could be interpreted as a **security under Howey**, and potentially as a **campaign contribution** or **quid pro quo** by the FEC. Before Sprint 9 ships, this needs legal review. Safest launch strategy:

- Position meTokens as **non-transferable loyalty badges** initially (soulbound/SBT flavor)
- Only unlock transferability + secondary market after legal review
- Leverage the existing Phase 16 transparency system for FEC-facing disclosure hooks

### Business framing

Three revenue sources flow to voters: political ads (high-value, $1.00/view), ballot issue ads (similar economics), and local voice ads (lower-value, $0.75/view, broader supply). A voter could earn from a governor race, a school-board bond measure, and a corner bakery's grand-opening ad in the same afternoon — a stronger engagement pitch than a pure political platform.

---

## Badge + Favorites System Design (Reference — implemented in Phase 19)

Built on two existing primitives:

- `PoliticianTopic` — curated tags ("Healthcare", "Climate Action", "Housing") that became the badge catalog
- `politician_initiatives` — issue positions politicians already declare

### Badge meaning per role

| Role | Badge Meaning | Example |
|---|---|---|
| Politician | "I champion this issue" — tied to their initiatives | 🏥 Healthcare Access, 🌱 Climate Action |
| Citizen (future) | "I care about this as a community member" | 🏫 Public Schools, 🏘️ Affordable Housing |
| Voter | "I watch campaigns about this topic" — auto-earned + self-selected | ✅ Watched 5 Healthcare Campaigns, ⭐ Education Supporter |

Voter badges split into **earned** (auto-granted at behavior thresholds) and **self-declared** (explicitly added to profile).

### Routes (implemented)

```
POST   /voter/favorites/{politician}        → add to favorites
DELETE /voter/favorites/{politician}        → remove from favorites
GET    /voter/favorites                     → voter's full favorites list

POST   /voter/badges/{topic}                → self-declare a badge
DELETE /voter/badges/{topic}                → remove self-declared badge
GET    /voter/badges                        → voter's badge profile

POST   /politician/badges/{topic}           → politician declares support
DELETE /politician/badges/{topic}           → remove
```

### UI integration points (partially done)

- **`/p/{slug}` (politician public profile)** — ⏳ pending: "Supporters" counter, badge row (pills with icons), "Add to Favorites" button
- **Voter dashboard** — ✅ done: "Politicians I Follow" list at `resources/views/standalone/voter/favorites/index.blade.php`
- **Voter watch page** — ⏳ pending: post-view badge-earned toast ("You've earned the 🏥 Healthcare Supporter badge!")

### Web3 connection (future)

- A voter holding a governor's meToken could auto-earn a "Token Holder" badge for that politician's topic set
- The favorites list could become the target list for XMTP direct messages (politician broadcasts to wallets in `voter_favorite_politicians`)
- Badge thresholds could unlock token-gated Songchain playlist access
