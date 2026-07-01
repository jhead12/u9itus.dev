Searched for regex `media_type|source_type` (`**/app/Models/PoliticalCampaign.php`), 1 result

Searched for files matching `**/PlatformSetting*.php`, 2 matches

Searched for files matching `**/app/Enums/*.php`, 6 matches

Searched for regex `deductCredit|deductCredits|charge.*credit` (`**/app/Services/**`), 3 results

Read [](file:///Users/jay/Documents/Github/u9itus.dev/app/Models/PoliticalCampaign.php#1-1), lines 1 to 80

Read [](file:///Users/jay/Documents/Github/u9itus.dev/app/Enums/CampaignType.php)

Read [](file:///Users/jay/Documents/Github/u9itus.dev/app/Services/PlatformSettingsService.php#1-1), lines 1 to 80

Searched for regex `revenue_per_view|voter_payout_per_view` (`**/config/u9itus.php`), 2 results

Searched for regex `campaign_type|media_type` (`**/database/migrations/**`), 8 results

Read [](file:///Users/jay/Documents/Github/u9itus.dev/config/u9itus.php#1-1), lines 1 to 100

Created memory file [](file:///Users/jay/Library/Application%20Support/Code/User/workspaceStorage/eb76c3d3a734072018d0e628d2fd14b2/GitHub.copilot-chat/memory-tool/memories/NzY3MWFiMjgtY2E4Ni00NTU5LThkYjctNTY5MTk4ZWE4OWY2/web3-integration-plan.md)

Searched for regex `head_enterprises_fee|deductCredits|record_view` (`**/app/Services/CampaignBillingService.php`), no results

Read [](file:///Users/jay/Documents/Github/u9itus.dev/app/Services/CampaignBillingService.php#200-200), lines 200 to 280

Now I have enough. Let me deliver a comprehensive, pragmatic cost + implementation plan.

---

## Part 1 — Cost Structure: What Web3 Actually Adds

Here's the honest per-view cost math based on the crtv3 patterns I reviewed.

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

Total added ops cost per view for full-stack Web3: **~$0.003** — comfortably absorbed by your existing $0.18–$0.30 margin.

### Proposed Pricing Tiers (using existing `PlatformSetting.user_tier` field)

Your `PlatformSetting` model already supports `user_tier` scoping with `effective_from/until` — no schema changes needed. Just add three tiers as canonical values:

```
'user_tier' column values:
  - 'standard'      → $1.00/view, YouTube/S3 only    (default, existing behavior)
  - 'verified_plus' → $1.05/view, adds Livepeer + Story Protocol per-video registration
  - 'sovereign'     → $1.10/view, adds MeToken economy + on-chain payouts
```

You'd also add a one-time **setup fee row** for the meToken tier — e.g., `platform_setting.key = 'metoken_setup_fee'`, `value = 25.00`, category = `web3`. That $25 covers your gas for DAI approval + factory call + your admin overhead.

**Important design principle:** *The politician chooses their tier at campaign creation, not account creation.* This means the same politician can run a cheap YouTube outreach campaign and a premium on-chain campaign side-by-side. The tier is a per-campaign flag, not a per-account one.

---

## Part 2 — Is the Video Layer Selectable?

Yes — you already have the mechanism. Your `PoliticalCampaign` model has [media_type](app/Models/PoliticalCampaign.php#L37) as a fillable enum column. Adding `livepeer` is a single ENUM migration matching the pattern in [hls_stream migration](database/migrations/2026_04_02_000004_add_hls_stream_media_type_to_campaigns.php).

The politician selects `media_type` in the campaign create form. If they pick `livepeer`, they consent to the +$0.05/view surcharge. The voter never sees a difference — they just watch a video.

Same story for voters: they never *choose* Web3. They just get a wallet address silently (via Account Kit) tied to their U9itus account. If they engage with a Web3-enabled campaign, they earn meTokens on top of USD. Otherwise, everything works as-is.

---

## Part 3 — Implementation Plan (Concrete Sprints)

I've verified the patterns match your existing architecture. Here's a phased plan that reuses your existing services:

### Sprint 7 — Web3 Foundation (Read-Only, Zero Risk)

**Goal:** Show on-chain meToken stats on politician profiles. No writes, no wallet, no user-facing crypto UX. Just enrichment.

Files to create:

- `app/Services/Web3/MeTokenSubgraphService.php` — mirrors your `BallotpediaService` pattern (24-hour cache, HTTP client, graceful fail-safe). Queries the public Goldsky endpoint `https://api.goldsky.com/api/public/project_cmh0iv6s500dbw2p22vsxcfo6/subgraphs/metokens/1.0.2/gn`. No auth required. Method: `getMeTokenByOwner(string $walletAddress): ?array`.
- Migration: `politicians.wallet_address` (nullable string, indexed) + `politicians.metoken_address` (nullable).
- View partial: `resources/views/standalone/public/partials/metoken-stats.blade.php` — displayed in the Dig Deeper section on `/p/{slug}`.
- Toggle in `platform_settings`: `web3_features_enabled` (default false) — hides everything until flipped.

**Cost to platform:** $0. **Risk:** minimal. This is pure enrichment on the transparency layer you already built in Phase 16.

### Sprint 8 — Livepeer as Selectable Media Type

Files to modify/create:

- Migration: extend `media_type` enum to include `livepeer`. Same DB::statement ALTER pattern.
- `app/Services/Video/LivepeerService.php` — server-side upload API wrapper (`POST /asset/request-upload`, then poll for `playbackId`).
- Update `resources/views/standalone/politician/campaigns/create.blade.php` to add a Livepeer option in the media source picker with a "+$0.05/view (censorship-resistant hosting)" label.
- Update the watch view player switch (`resources/views/standalone/voter/watch.blade.php`) — add an `elseif ($campaign->media_type === 'livepeer')` branch using Livepeer's `@livepeer/react` player (or vanilla HLS.js — you already have `hls_stream` branch).
- `PoliticalPaymentService::chargePerView()` — read tier from campaign, apply surcharge.

**Cost impact:** Only politicians who select Livepeer pay the surcharge. Existing YouTube campaigns unchanged.

### Sprint 9 — MeToken Opt-In for Politicians

Files to create:

- `app/Services/Web3/MeTokenSetupService.php` — orchestrates the one-time $25 setup fee via Stripe (existing `CampaignBillingService::createPurchaseIntent` pattern) → on payment success, dispatches `DeployMeTokenJob` to a queue.
- `app/Jobs/DeployMeTokenJob.php` — server-side signing via a platform hot wallet (or admin-signed manual mint via crtv3's factory). Calls `METOKEN_FACTORY.create` on Base. On success, updates `politicians.metoken_address`.
- New route: `POST /politician/web3/enable-metoken` — shows the $25 charge confirmation, on success queues the job.
- Onboarding phase addition: Politician onboarding gets a new optional phase "Enable On-Chain Loyalty Token" (uses the existing `OnboardingService` phase machinery).

**Consent flow:** The politician sees a clear pricing sheet:
> "Enable your loyalty token — $25 one-time setup + $0.10/view (+$0.05 over standard). Your supporters can hold your token as proof of allegiance. Cancel anytime."

### Sprint 10 — Voter Web3 (Silent Onboarding)

Files:

- `app/Services/Web3/SmartAccountService.php` — calls Alchemy Account Kit REST API to provision a smart wallet at voter registration (transparent, no seed phrase). Store address in `voters.smart_account_address`.
- Alchemy Paymaster policy configured to sponsor voter gas up to N txs/month.
- Voter dashboard: new "On-Chain Earnings" card showing meToken balance across all politicians they've supported.

### Sprint 11 — Story Protocol IP Registration (Per Campaign)

Files:

- `app/Services/Web3/StoryProtocolService.php` — mirrors crtv3's `services/story-protocol.ts`. Called from `CampaignController::store` on publish success, for campaigns with `media_type = livepeer` only.
- Adds `political_campaigns.story_ip_id`, `story_ip_registered_at`, `story_license_terms_id` columns.
- Displayed as a "🔗 Registered as IP" badge on the campaign card.

### Sprint 12 — On-Chain Payout Layer (0xSplits)

Files:

- `app/Services/Web3/SplitsPayoutService.php` — batches voter payouts into a single split contract per weekly cycle. Runs alongside existing `PayPalPayoutService` (voters opt in per payout method).
- `voters.payout_preference` enum: `paypal | cashapp | onchain_usdc | metoken`.

### Sprint 13 — Music Layer (Songchain-style)

Files:

- `app/Services/Web3/LensFeedService.php` — reads Lens/Orb feeds for a politician's connected Lens account.
- `politicians.lens_account_id` column.
- New public page section: "Campaign Playlist" on `/p/{slug}` — voter meToken holders unlock the full curated playlist.

---

## Part 4 — Governance & Rollout

### Feature flag protection

Add a `platform_settings` row:

```
key: web3_features_enabled
value: false
category: web3
description: Master kill switch for all Web3 integrations
```

Every Web3 code path checks `PlatformSettingsService::get('web3_features_enabled', null, false)` before executing. If flipped off, the platform reverts to pure Web2 mode instantly. This matches your existing "Clear Cache" admin pattern.

### Testing pattern

Mirror your existing service test pattern ([BallotpediaService](app/Services/BallotpediaService.php) → `BallotpediaServiceTest`). Each new Web3 service gets:

- HTTP fake for the RPC/subgraph endpoint (`Http::fake()`)
- Cache assertion (24-hour TTL)
- Graceful failure test (returns null, not thrown exception)
- Rate-limit / 429 test

The 275-test suite grows to ~330 with full Web3 coverage.

### Regulatory note (worth flagging)

A politician-branded meToken with monetary value that's earned by watching political ads could be interpreted as a **security under Howey**, and potentially as **campaign contribution** or **quid pro quo** by the FEC. Before Sprint 9 ships, this needs legal review. The safest launch strategy:

- Position meTokens as **non-transferable loyalty badges** initially (soulbound / SBT flavor)
- Only unlock transferability + secondary market after legal review
- FEC-facing disclosure hooks live in your existing Phase 16 transparency system — leverage that

### Recommended immediate step

Start with **Sprint 7 (MeToken subgraph read-only)** — it's zero-cost, zero-risk, purely additive, and gives you a live demo you can show partners at creativeplatform to align on deeper integration. That single service class is ~150 lines of PHP and follows your existing Ballotpedia/OpenSecrets pattern exactly.

Want me to draft the `MeTokenSubgraphService.php` service class + migration in a follow-up so you can review the actual code shape?Every Web3 code path checks `PlatformSettingsService::get('web3_features_enabled', null, false)` before executing. If flipped off, the platform reverts to pure Web2 mode instantly. This matches your existing "Clear Cache" admin pattern.

### Testing pattern

Mirror your existing service test pattern ([BallotpediaService](app/Services/BallotpediaService.php) → `BallotpediaServiceTest`). Each new Web3 service gets:

- HTTP fake for the RPC/subgraph endpoint (`Http::fake()`)
- Cache assertion (24-hour TTL)
- Graceful failure test (returns null, not thrown exception)
- Rate-limit / 429 test

The 275-test suite grows to ~330 with full Web3 coverage.

### Regulatory note (worth flagging)

A politician-branded meToken with monetary value that's earned by watching political ads could be interpreted as a **security under Howey**, and potentially as **campaign contribution** or **quid pro quo** by the FEC. Before Sprint 9 ships, this needs legal review. The safest launch strategy:

- Position meTokens as **non-transferable loyalty badges** initially (soulbound / SBT flavor)
- Only unlock transferability + secondary market after legal review
- FEC-facing disclosure hooks live in your existing Phase 16 transparency system — leverage that

### Recommended immediate step

Start with **Sprint 7 (MeToken subgraph read-only)** — it's zero-cost, zero-risk, purely additive, and gives you a live demo you can show partners at creativeplatform to align on deeper integration. That single service class is ~150 lines of PHP and follows your existing Ballotpedia/OpenSecrets pattern exactly.

Want me to draft the `MeTokenSubgraphService.php` service class + migration in a follow-up so you can review the actual code shape?

Searched for regex `citizen|Citizen` (`**/app/**/*.php`), 1 result

Searched for regex `'citizen'|role.*citizen|local.*ad` (`**/config/**`), no results

You're right — the plan I drafted only covered politicians + voters. A **Citizen** role (non-politician who runs local ads) is a separate user segment. Let me place it in the architecture properly.

I searched the codebase — there's no `citizen` role yet. So this is a **new user segment to design**, not an existing one to gate. Before I lock the design, one thing worth clarifying:

Analyzing your answers...

Got it — **general "Local Voice" tier**. Here's how it fits into the architecture cleanly.

---

## The Full Four-Role Model

Your current stack has three roles (`admin`, `politician`, `voter`). The Citizen is a fourth role that runs *between* politician and voter — someone who **pays to distribute content**, but at a smaller scale, without the FEC / election-commission burden.

| Role | Pays or Earns? | Content Type | Web3 Eligible? |
|---|---|---|---|
| **Admin** | — | Moderation | — |
| **Politician** (federal/state/municipal) | Pays | Political campaigns, gov. business | Verified+ for all; Sovereign for governors only |
| **Citizen** _(new)_ | Pays | Local ads: business, community, ballot issues | Verified+ standard; MeToken **opt-in via community threshold** |
| **Voter** (all viewers) | Earns | — | All voters silently get smart wallets |

### Why Citizen ≠ Politician (important distinction)

- **Politicians** are subject to FEC / state election law. Their `political_office`, `governance_level`, `party`, and district targeting exist for compliance.
- **Citizens** are commercial or civic actors. They don't need `governance_level`, but they *do* need:
  - **Locality binding** — a Citizen's ad only distributes to voters within a `target_zip_radius` or `target_city` scope (much tighter than a state-level politician campaign).
  - **Content-type declaration** — `citizen_ad_type` enum: `local_business | community_notice | ballot_issue | general_announcement`. This routes moderation rules.
  - **Simpler verification** — email + address confirmation; no `.gov`/`.mil` requirement.

### Proposed Citizen Pricing

Citizens should pay **less** than politicians (smaller reach, lower stakes, no compliance overhead), but the voter payout stays the same to keep the value proposition consistent.

| Component | Politician (Standard) | Citizen (Standard) |
|---|---|---|
| Cost per view | $1.00 | **$0.75** |
| Voter payout | $0.50 | $0.50 |
| Referral commission (10%) | $0.05 | $0.05 |
| Platform net | ~$0.45 | **~$0.20** |
| Reach cap | Unlimited (subject to targeting) | **500-view daily cap per campaign** (prevents concentration) |
| Min campaign spend | $50 | **$10** (low barrier for a local shop) |

The $0.75/view figure keeps the platform margin thinner but lets a corner restaurant reach 100 neighbors for $75 — that's fundable from a marketing budget, not a campaign war chest.

### Web3 Placement for Citizens

Here's the interesting part — **MeTokens don't quite fit** for most Citizens (a pizza place doesn't need a personal loyalty token). But there's an elegant middle option:

| Citizen Tier | Cost | Web3 Layer |
|---|---|---|
| **Standard** | $0.75/view | YouTube/S3 media, PayPal payouts. No wallet needed. |
| **Verified+ Local** | $0.80/view | Livepeer decentralized hosting, Story Protocol IP registration on the ad. Good for community groups who want permanent record. |
| **Neighborhood Token** _(opt-in, communal)_ | $0.85/view + one-time $10 registration | **The MeToken is issued to a `neighborhood_group` (e.g., "Downtown SF Merchants Association"), not an individual.** Multiple Citizens co-mint under one token. Members earn a share of collective ad reach. |

**The Neighborhood Token idea** solves the "why would a pizza shop need a token" problem. Instead, a *group of local shops* (business improvement districts, farmers-market vendors, HOA boards) share a MeToken. Voters who watch their ads earn the group's meToken. This makes economic sense at the community scale — a token backed by 20 local businesses is more meaningful than one for a single shop.

### Ballot Issue Citizens (Special Case)

Citizens running **ballot-issue ads** ("Vote Yes on Prop 12") are politically-adjacent — they fall under state PAC/committee rules. For these:

- Require a `pac_registration_id` field (state PAC number)
- Route through admin approval queue just like politician campaigns
- Same $1.00/view pricing as politicians (they have similar reach ambitions)
- Eligible for the same on-chain transparency layer

This creates a clean four-way split of ad content: **candidate campaigns, ballot issues, community notices, and commercial local ads** — each with appropriate moderation and pricing.

### Schema Additions (minimal)

Two new tables + one enum modification. Everything else reuses your existing `PoliticalCampaign` (or a sibling `citizen_campaigns` table if you want cleaner separation — I'd recommend the latter for compliance clarity):

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

### Role Middleware

Adds a fourth role in Spatie Permission: `citizen`. Registration flow becomes:

- `/register/politician` — existing
- `/register/voter` — existing
- `/register/citizen` — new, with address + ad-type selection

### Voter Experience (unchanged)

Voters don't need to know or care whether the ad came from a politician or a citizen. The watch flow, payout ($0.50), referral commission, and fraud-prevention pipeline all work identically. The Citizen distinction is purely on the *supply* side.

### Updated Sprint Plan (with Citizen inserted)

| Sprint | Focus | Web3? |
|---|---|---|
| Sprint 7 | MeToken subgraph read-only enrichment (governor profiles) | Yes (read-only) |
| **Sprint 7.5** _(new)_ | **Citizen role foundation** — role, registration, `citizen_campaigns` table, geolocated targeting, ad-type moderation | No |
| Sprint 8 | Livepeer as selectable `media_type` (available to politicians AND citizens) | Yes |
| **Sprint 8.5** _(new)_ | **Neighborhood Groups schema + admin UI** — group creation, member invites, shared campaign attribution | No |
| Sprint 9 | MeToken opt-in — governor candidates (Sovereign tier) | Yes |
| **Sprint 9.5** _(new)_ | **Neighborhood Token opt-in** — group-level MeToken deployment via existing MeTokenSetupService, unlocked at 3+ member threshold | Yes |
| Sprint 10 | Voter smart wallet provisioning (unchanged — works for all ad types) | Yes |
| Sprint 11 | Story Protocol IP registration (available to politicians AND citizens with Verified+ tier) | Yes |
| Sprint 12 | 0xSplits payout distribution (all voters, all ad sources) | Yes |
| Sprint 13 | Songchain music playlists (available to politicians AND neighborhood groups) | Yes |

### Business Framing (updated)

The platform now has **three revenue sources** flowing to voters:

1. **Political ads** — democracy engagement, high-value ($1.00/view)
2. **Ballot issue ads** — civic-engagement, similar economics
3. **Local voice ads** — community/commercial, lower-value ($0.75/view), broader supply

A voter in Oakland could earn from a governor race, a local school-board bond measure, *and* a corner bakery's grand-opening ad — all in the same afternoon. That's a much stronger "why should I stay engaged" pitch than a pure political platform.

### Immediate Question

Do you want me to keep the current political/citizen data models **separate** (two parallel tables: `political_campaigns` + `citizen_campaigns`) or **unified** (add a `campaign_source_type` column and consolidate)?

- **Separate** is safer for legal reasons — cleaner audit trails, easier to remove one without breaking the other, distinct moderation queues.
- **Unified** is easier to build and share the voter watch flow.

Given your compliance-heavy stack (FEC, transparency, audit logs), I'd recommend **separate tables** — but they can share the `view_sessions` and payout infrastructure downstream.

Searched for regex `badges|badge|favorite|favourit|endorsement` (`**/database/migrations/**`), 1 result

Searched for regex `voter_profile|voter.*profile|profile_photo|bio|interests` (`**/app/Models/Voter.php`), no results

Searched for regex `wallet_balance|referral_code|trust_score|profile` (`**/database/migrations/2026_02_05_000001_create_wix_political_tables.php`), 5 results

Read [](file:///Users/jay/Documents/Github/u9itus.dev/database/migrations/2026_06_23_000001_create_politician_song_picks_table.php#1-1), lines 1 to 60

Searched for regex `politician_topics|PoliticianTopic` (`**/app/Models/**`), 3 results

Read [](file:///Users/jay/Documents/Github/u9itus.dev/database/migrations/2026_02_05_000001_create_wix_political_tables.php#1-1), lines 1 to 80

Searched for regex `politician_initiatives|PoliticianInitiative` (`**/database/migrations/**`), 3 results

Read [](file:///Users/jay/Documents/Github/u9itus.dev/app/Models/PoliticianTopic.php#1-1), lines 1 to 40

Good — now I have a clear picture of what exists. Here's the full design:

---

## Badge + Favorites System Design

You already have the two key primitives this feature needs:

- `PoliticianTopic` — curated tags like "Healthcare", "Climate Action", "Housing" — these *become* the badge catalog
- `politician_initiatives` — issue positions that *politicians* already declare

The badge system is an **extension of topics** that flows in both directions: politicians *declare* support for topics (they do this already via initiatives), and voters/citizens *signal* which topics they care about. The favorites list is a simple join table.

---

### What "Support Badges" Mean Per Role

| Role | Badge Meaning | Example |
|---|---|---|
| **Politician** | "I champion this issue" — tied to their initiatives | `🏥 Healthcare Access`, `🌱 Climate Action` |
| **Citizen** | "I care about this as a community member" | `🏫 Public Schools`, `🏘️ Affordable Housing` |
| **Voter** | "I watch campaigns about this topic" — auto-earned + self-selected | `✅ Watched 5 Healthcare Campaigns`, `⭐ Education Supporter` |

Voter badges split into two subtypes:
- **Earned badges** — automatically granted when behavior thresholds are met (watched 5 campaigns on a topic, referred a voter, etc.)
- **Self-declared badges** — voter explicitly adds "I support public transit" to their profile

---

### Schema (3 tables)

**1. Reuse `politician_topics` as the badge catalog** — no new table needed. Just add two columns:

```php
// Migration: add badge fields to existing politician_topics
$table->string('badge_icon_url')->nullable();    // SVG/PNG asset path
$table->string('badge_color', 7)->default('#6366f1'); // hex color
$table->boolean('voter_selectable')->default(true);    // can voters self-add this?
$table->boolean('auto_earned')->default(false);        // system-granted only
```

**2. `profile_badges`** — polymorphic: politicians, voters, and citizens all use one table

```php
Schema::create('profile_badges', function (Blueprint $table) {
    $table->id();
    $table->morphs('badgeable');              // badgeable_type + badgeable_id
                                              // → Politician, Voter, (later Citizen)
    $table->foreignId('topic_id')
          ->constrained('politician_topics')
          ->cascadeOnDelete();
    $table->enum('badge_type', [
        'self_declared',   // user chose it
        'earned_views',    // watched N campaigns on this topic
        'earned_referral', // referred voters in this topic
        'endorsed',        // another politician endorsed them (future)
    ])->default('self_declared');
    $table->unsignedInteger('earned_threshold')->nullable(); // e.g. 5 views
    $table->timestamp('earned_at')->nullable();
    $table->boolean('is_public')->default(true);
    $table->timestamps();

    $table->unique(['badgeable_type', 'badgeable_id', 'topic_id']); // one badge per topic per profile
    $table->index(['badgeable_type', 'badgeable_id']);
});
```

**3. `voter_favorite_politicians`** — favorites list

```php
Schema::create('voter_favorite_politicians', function (Blueprint $table) {
    $table->id();
    $table->foreignId('voter_id')->constrained()->cascadeOnDelete();
    $table->foreignId('politician_id')->constrained()->cascadeOnDelete();
    $table->timestamp('favorited_at')->useCurrent();

    $table->unique(['voter_id', 'politician_id']); // no duplicates
    $table->index('voter_id');
    $table->index('politician_id');               // query "who favorited this politician"
});
```

---

### Model Additions

**Trait `HasProfileBadges`** — shared by `Politician`, `Voter`, and (future) `Citizen`:

```php
// app/Traits/HasProfileBadges.php
trait HasProfileBadges
{
    public function badges(): MorphMany
    {
        return $this->morphMany(ProfileBadge::class, 'badgeable');
    }

    public function publicBadges(): MorphMany
    {
        return $this->badges()->where('is_public', true)
                              ->with('topic');
    }

    public function addBadge(int $topicId, string $type = 'self_declared'): ProfileBadge
    {
        return $this->badges()->firstOrCreate(
            ['topic_id' => $topicId],
            ['badge_type' => $type, 'earned_at' => now()]
        );
    }
}
```

**`Voter` model additions:**

```php
public function favoritePoliticians(): BelongsToMany
{
    return $this->belongsToMany(Politician::class, 'voter_favorite_politicians', 'voter_id', 'politician_id')
                ->withTimestamps();
}
```

**`Politician` model additions:**

```php
// How many voters have favorited this politician — useful for analytics
public function favoritedByVoters(): BelongsToMany
{
    return $this->belongsToMany(Voter::class, 'voter_favorite_politicians', 'politician_id', 'voter_id')
                ->withTimestamps();
}
```

---

### Auto-Earned Badge Logic

Hook into the existing `PoliticalViewService` — after `completeView()` fires, check the topic of the campaign watched:

```php
// In PoliticalViewService::completeView() — after payout logic
$topicIds = $session->campaign->topics->pluck('id');
foreach ($topicIds as $topicId) {
    $viewCount = $voter->viewSessions()
        ->whereHas('campaign.topics', fn($q) => $q->where('politician_topics.id', $topicId))
        ->where('status', 'completed')
        ->count();

    if ($viewCount >= 5) {
        $voter->addBadge($topicId, 'earned_views');
    }
}
```

This reuses your existing `view_sessions` + `campaign_topics` pivot without any new tracking overhead.

---

### Routes

```
// Voter favorites
POST   /voter/favorites/{politician}        → add to favorites
DELETE /voter/favorites/{politician}        → remove from favorites
GET    /voter/favorites                     → voter's full favorites list

// Badge management (all roles)
POST   /voter/badges/{topic}               → self-declare a badge
DELETE /voter/badges/{topic}               → remove self-declared badge
GET    /voter/badges                        → voter's badge profile

POST   /politician/badges/{topic}          → politician declares support
DELETE /politician/badges/{topic}          → remove

// Public read (no auth)
GET    /p/{slug}/supporters                → how many voters favorited + badge alignment
```

---

### UI Integration Points

**On `/p/{slug}` (politician public profile)** — already has initiatives and transparency sections. Add below initiatives:

- "Supporters" counter: `X voters have favorited this candidate`
- Badge row: the politician's declared topic badges (pills with icons)
- "Add to Favorites" button for logged-in voters — single POST, optimistic Alpine.js toggle, no page reload

**On voter profile/dashboard:**

- "My Interests" badge shelf — self-declared + earned badges displayed as colored chips
- "Politicians I Follow" card — favorite politicians list with quick-access to their latest campaign

**On voter watch page** — after completing a watch session:

> *"You've now watched 5 Healthcare campaigns — you've earned the 🏥 Healthcare Supporter badge!"*

This fires via the existing post-view survey flow — no new JS needed.

---

### Web3 Connection

This badge system plugs directly into the MeToken layer for the governor tier:

- A voter holding a governor's meToken **automatically earns a special "Token Holder" badge** for that politician's topic set
- The favorites list becomes the *newsletter list* for XMTP direct messages — the politician's XMTP broadcast targets wallets in `voter_favorite_politicians`
- Badge thresholds (e.g., "Watched 10+ campaigns") can unlock token-gated Songchain playlist access on that politician's profile

---

The three tables above (`politician_topics` modifications + `profile_badges` + `voter_favorite_politicians`) are the only schema changes needed. The `HasProfileBadges` trait handles all three user types with zero duplication. Want me to write the full migration files?