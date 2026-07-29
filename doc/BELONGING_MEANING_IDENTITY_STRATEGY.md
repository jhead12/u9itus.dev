# Belonging, Meaning, and Identity — Product & Marketing Direction
_Prepared: 2026-07-28 — Refined 2026-07-29 after Phase 2 (Neighborhood Groups, People Near You, Guest Trial Mode) shipped_

## Why this doc exists

[MARKETING_FINANCIAL_GOALS.md](MARKETING_FINANCIAL_GOALS.md) is entirely payout-first: every CTA, every channel, every success metric is denominated in dollars ("earn $0.50/view," CAC, LTV). That framing works for a single acquisition sprint, but it has three structural problems:

1. **It's a commodity pitch.** Any "get paid to watch ads" app can outbid us on payout rate. Nothing about $0.50/view is defensible — a competitor drops in tomorrow at $0.60/view and our funnel breaks. Belonging, meaning, and identity are not price-shoppable; they compound with tenure and can't be undercut by a competitor's rate card.
2. **It risks the overjustification effect.** Psychology research on paying people for tasks they'd otherwise do for intrinsic reasons (civic duty, curiosity, values) consistently shows the payment *crowds out* the intrinsic motive rather than adding to it — once "watch for money" is the frame, users optimize for view-count, not persuasion, and churn the moment payout drops or a better-paying app appears. `MARKETING_FINANCIAL_GOALS.md`'s own retention target (≥40–50% 30-day) is fighting this effect head-on with money alone.
3. **It's already flagged as regulatory risk.** The same doc lists *"FTC scrutiny of earnings claims"* and *"FEC exposure for paying voters to watch political ads"* as High-severity risks. A money-led brand voice makes both worse; a belonging/meaning/identity-led brand voice — where earning is a nice-to-have, not the headline — reduces exposure on both fronts for free.

The product groundwork for the alternative already exists. This doc inventories what's built, lays out what to build next per pillar, and rewrites the marketing posture around it — without abandoning the per-view economics, which stay as the retention/monetization layer underneath.

---

## Current state — what's actually shipped per pillar

Grounded in the current codebase, not aspiration:

| Pillar | Shipped | Evidence |
|---|---|---|
| **Self-identity** | ✅ **Badge visibility control** — self-declared badges default private, per-badge public/private toggle, full self-declare/remove UI on `/voter/profile` ("My Badges") | [HasProfileBadges.php](../app/Traits/HasProfileBadges.php), [BadgeController.php](../app/Http/Controllers/Standalone/BadgeController.php), `voter.badges.visibility` route |
| **Self-identity** | ✅ **Landing page reframe** — "Your Civic Identity" section on the welcome page, positioned before the revenue/payout pitch | [welcome.blade.php](../resources/views/welcome.blade.php) |
| **Self-identity** | Profile badges (self-declared, earned_views, earned_referral, token_holder, inferred_discourse), one badge per topic per profile | [ProfileBadge.php](../app/Models/ProfileBadge.php), [profile_badges migration](../database/migrations/2026_07_01_000002_create_profile_badges_table.php) |
| **Self-identity** | Favoriting: politicians, boundaries, causes, ballot measures | [voter_favorite_causes](../database/migrations/2026_07_27_000002_create_voter_favorite_causes_table.php), [voter_favorite_ballot_measures](../database/migrations/2026_07_27_000003_create_voter_favorite_ballot_measures_table.php) |
| **Meaning-making** | One-note-per-politician voter journal (private reflection) | [VoterPoliticianNote.php](../app/Models/VoterPoliticianNote.php), [PoliticianNoteController.php](../app/Http/Controllers/Standalone/PoliticianNoteController.php) |
| **Meaning-making** | Topic-matched campaign alerts — voter gets emailed when a new campaign matches a Cause they favorited | [NotifyVoterOfMatchingCampaigns.php](../app/Jobs/NotifyVoterOfMatchingCampaigns.php), [CauseCampaignMatchService.php](../app/Services/CauseCampaignMatchService.php) |
| **Meaning-making** | ✅ **Guest Trial Mode** — admin-toggled, time-boxed window where an anonymous visitor is silently provisioned into a flagged voter session and can favorite, note, and browse with zero login screen. Placed under meaning-making, not acquisition: the whole point is letting a visitor *reach* the outcome/feedback-loop features (favorites, notes, "people near you") before a signup wall ever interrupts that experience — the money routes stay gated the entire time. | [ProvisionGuestVoterSession.php](../app/Http/Middleware/ProvisionGuestVoterSession.php), [BlockGuestFromMonetization.php](../app/Http/Middleware/BlockGuestFromMonetization.php), [PruneExpiredGuestVoters.php](../app/Console/Commands/PruneExpiredGuestVoters.php) |
| **Belonging** | ✅ **Neighborhood Groups MVP** — create, join/leave, admin settings, public `/groups/{slug}` page with member count and scoped URLs | [GroupController.php](../app/Http/Controllers/Standalone/GroupController.php), [PublicGroupController.php](../app/Http/Controllers/Standalone/PublicGroupController.php), [NeighborhoodGroup.php](../app/Models/NeighborhoodGroup.php) |
| **Belonging** | ✅ **Group member management** — member list, owner/co-admin role distinction (`isOwner()`/`isAdmin()`), promote/demote, remove | [GroupMemberController.php](../app/Http/Controllers/Standalone/GroupMemberController.php) |
| **Belonging** | ✅ **Group-scoped events** — groups can host `CivicEvent`s (via polymorphic `host`) alongside citizens/politicians, shown on the public group page | [GroupEventController.php](../app/Http/Controllers/Standalone/GroupEventController.php), [GroupEventRequest.php](../app/Http/Requests/GroupEventRequest.php) |
| **Belonging** | ✅ **"People near you" signal** — Causes and Ballot Measures directory/show pages show a state-scoped, aggregate "N nearby supporters" count (no names/avatars) alongside a nationwide total | [CauseBrowseController.php](../app/Http/Controllers/Standalone/CauseBrowseController.php), [BallotMeasureBrowseController.php](../app/Http/Controllers/Standalone/BallotMeasureBrowseController.php) |
| **Belonging** | Group badges, group themes, and peer-to-peer comments/reactions remain unshipped — see roadmap below | — |

**Read on this:** as of Phase 2, all three pillars have shipped, user-facing surface area — belonging went from 0% to the pillar with the most net-new code in this phase (Groups MVP + member roles + group events + people-near-you). Guest Trial Mode is the first piece of infrastructure that spans pillars by design: it's meaning-making's low-friction on-ramp, but it also lets a visitor experience belonging (people-near-you counts, a public group page) before ever hitting a signup wall. Still open: group badges/themes (belonging), outcome tracking and civic year-in-review (meaning-making), and the `/p/{slug}` badge chip UI (self-identity).

**Refined finding from shipping Phase 1:** the original scope estimate ("expose the existing `is_public` flag as a toggle") undersold the work — there was **zero front-end UI calling the badge routes at all**, so "add a toggle" turned into "build the self-declare UI from scratch, then add the toggle." The general lesson for the rest of this roadmap: a data-layer primitive existing (a column, a route, a relation) is not evidence a feature is reachable by a user. Before scoping Belonging or Meaning-making items below as "just a UI change," audit whether any template actually renders/calls the underlying code path — the Neighborhood Groups P0 item is especially at risk of this, since `Creative.md` describes schema and services in detail but no views.

---

## Product roadmap, per pillar

### 1. Belonging — make other people visible

The core gap: everything today is voter↔politician (parasocial). Nothing is voter↔voter. Belonging requires seeing peers, not just following a politician.

| Priority | Item | Notes |
|---|---|---|
| ✅ Done | ~~Ship Neighborhood Groups (Sprint 8.5 core, not the full Patreon-funding scope)~~ — group creation, membership, public `/groups/{slug}` page with member count | Landed 2026-07-29. Scope was cut as planned: `neighborhood_groups` + `group_memberships` only; `group_contributions`/`group_campaign_budget` (Patreon funding) still deferred. |
| ✅ Done | ~~"People near you" signal~~ — Cause/Ballot Measure directory + show pages show a state-scoped aggregate count of other voters who favorited it, plus a nationwide total for national items | Landed 2026-07-29 via [CauseBrowseController.php](../app/Http/Controllers/Standalone/CauseBrowseController.php)/[BallotMeasureBrowseController.php](../app/Http/Controllers/Standalone/BallotMeasureBrowseController.php). Shipped as aggregate-counts-only, no avatars — applying the self-asserted-vs-system-computed test below as originally specified. |
| ✅ Done | **Group member management** — member list page, owner vs. promoted co-admin role split, promote/demote, remove | Landed 2026-07-29 via [GroupMemberController.php](../app/Http/Controllers/Standalone/GroupMemberController.php). Not originally scoped as its own line item — surfaced as a natural follow-on once Groups MVP shipped (a group with members but no way to see/manage them wasn't a complete P0). |
| ✅ Done | **Group-scoped events** — groups can host events (RSVP, virtual/in-person) alongside citizens/politicians, surfaced on the public group page | Landed 2026-07-29 via [GroupEventController.php](../app/Http/Controllers/Standalone/GroupEventController.php), reusing the existing `CivicEvent` polymorphic host relation. Also not originally scoped — same rationale as member management: a group needs something to *do* to be more than a directory listing. |
| P1 | Group badges (`badge_type = group_member`) + group themes, as already spec'd in Creative.md §8.5–8.6 | Now unblocked — group membership, member management, and events have all shipped, so theming/badging a group is no longer theming an empty shell. |
| P1 | Lightweight group activity feed — "3 members watched [Politician]'s new campaign this week," "Group hit 50% of its signature-drive goal" | Doesn't require chat/comments (moderation surface); aggregate stats are lower-risk and still create belonging. The new group-events data (RSVPs, upcoming events) is a ready-made input for this feed. |
| P2 | Peer-to-peer comments or reactions on a Cause/Ballot Measure page | Real community, but is a moderation commitment — sequence after Group MVP proves demand. |

### 2. Meaning-making — close the feedback loop

The gap: users can favorite and watch, but nothing tells them what happened as a result. Meaning comes from seeing outcomes, not just taking actions.

| Priority | Item | Notes |
|---|---|---|
| ✅ Done | **Guest Trial Mode** — admin-toggled, time-boxed window where an anonymous `/voter/*` visit is silently upgraded into a flagged voter session (no login screen), so a first-time visitor reaches the meaning/belonging surfaces (favorites, notes, group page, people-near-you counts) with zero signup friction | Landed 2026-07-29 via [ProvisionGuestVoterSession.php](../app/Http/Middleware/ProvisionGuestVoterSession.php). Money routes (`earnings`, `payout`, `referrals`, Early-bank SSO) stay blocked via `BlockGuestFromMonetization`, and 2FA enforcement is skipped for guests — both deliberate, so the trial only ever demonstrates meaning/belonging, never the payout mechanic. Registering upgrades the same session in place (favorites/notes carry over); expired guest accounts are pruned daily by `guests:prune-expired`. Admin toggle lives on `/admin/platform-settings`. |
| P0 | **Outcome tracking on favorited races/measures** — after an election, show "you favorited 3 candidates; 2 won" / "the ballot measure you supported passed" | This is the single highest-leverage meaning item — it converts "I watched a video" into "I was part of something that happened." Needs an election-results ingestion step (may already be adjacent to existing Ballotpedia/Vote Smart integrations — check `app/Services/BallotpediaService.php` for a results endpoint before building new). |
| P0 | **Impact summary / "civic year in review"** — aggregate: causes supported, notes written, campaigns watched by topic, elections engaged with | Purely a read-model over data already captured (`voter_politician_notes`, `voter_favorite_causes`, view history). No new capture needed, just a synthesis view. |
| P1 | Surface the existing matched-campaign alert as an in-app moment, not just email — "This matches something you care about" framing at the point of watching, reinforcing *why* before *how much* | Small UI change to the watch flow; reuses `CauseCampaignMatchService`. |
| P1 | Expand the private note into a lightweight "why I support this" prompt, with an opt-in to make individual notes public on the voter's profile | Turns private reflection into identity-signal content when the user chooses — bridges meaning-making into self-identity. |
| P2 | Post-election reflection prompt — "Did watching this change your view?" (already adjacent to the existing post-view survey mentioned in wiki/Business-Model.md) | Qualitative signal, also useful as a marketing testimonial source if opt-in. |

### 3. Self-identity — add privacy granularity, then depth

The gap isn't features, it's controls and range. Political identity is high-stakes to expose; the badge/favorite system needs better defaults before it needs more badge types.

| Priority | Item | Notes |
|---|---|---|
| ✅ Done | ~~Badge/favorite visibility UI~~ — self-declared badges default private, per-badge public/private toggle, full self-declare/remove UI shipped on `/voter/profile` | Landed 2026-07-28. Earned/inferred badges intentionally **excluded** from the toggle — see principle below. |
| P1 | Ship the deferred badge chip UI on `/p/{slug}` (noted as pending in Creative.md — data layer done, rendering not wired in) | Low-effort, already-designed feature sitting half-finished. |
| P1 | Profile Themes (Sprint 8.6, already spec'd) — preset + custom + group + politician-supporter themes | Ship after Group MVP (belonging P0) so there's a real group theme to wear, not just presets. |
| P2 | Broaden identity beyond party/candidate affiliation — badges for civic *actions* (first note written, first campaign watched to completion, referred a neighbor) alongside issue-based badges | Reduces the risk that self-identity on this platform narrows to "who I vote for," which is both a privacy risk and a less inclusive identity surface than "how I participate." |

#### Principle established: self-asserted vs. system-computed visibility

Shipping the badge toggle forced a real decision — should *earned* badges (`earned_views`, `earned_referral`) and *inferred* badges (politician discourse detection) get the same private-by-default treatment as self-declared ones? The answer landed on **no**, for a reason that generalizes to every remaining item in this doc:

- **Self-asserted content** (a voter explicitly declares "I champion Healthcare Access") is a personal political stance the user chose to state — it carries real-world exposure risk (employer, family, harassment) and should default private, opt-in to public.
- **System-computed content** (the platform observed you watched 5 campaigns, or detected a politician's public record) is either evidence of *behavior* rather than a declared stance, or is already-public information. Keeping it always-visible preserves its value as a trust signal — a badge only means something if it can't be selectively hidden when inconvenient.

**Apply this same test before defaulting visibility on any belonging/meaning-making item below:**
- Outcome tracking ("you favorited 3 candidates, 2 won") — computed from public election results → default visible is fine, same logic as earned badges.
- Group activity feed ("3 members watched X this week") — aggregate/anonymized system observation → default visible, no individual exposure.
- The private note → public "why I support this" prompt (P1 below) — this **is** self-asserted, high-exposure content → must stay opt-in, same as badges.
- "People near you" signal (belonging P0) — this is closer to self-asserted (it reveals *which* causes a specific nearby person favorited) → default to aggregate counts only, treat named/avatar-level exposure as opt-in, not default-on.

---

## Marketing repositioning

The product changes above only matter if marketing stops selling the app as a payout app. Concretely, per channel from `MARKETING_FINANCIAL_GOALS.md`:

### Reframe the headline pitch

| Old (money-first) | New (belonging/meaning/identity-first, money as support) |
|---|---|
| "Earn $0.50 to watch this candidate's message" | "See where your neighborhood stands. Watch, weigh in, and get paid for your time." |
| "Get paid to watch political ads" | "Be part of the decisions that shape your district — and get paid while you're at it" |
| Referral pitch: "$10 + 10% of their earnings" | "Bring your neighbors in — build the group, not just the payout" |

Money stays in the copy (it's a real, honest benefit and removing it entirely would be misleading) — it moves from the headline to the second sentence.

### Channel-by-channel changes

- **Channel 1 (Programmatic SEO + landing page)** — ✅ **Partially live**: the welcome page now leads with a "Your Civic Identity" section (badges, privacy control) placed *before* the revenue pitch, not after. Still pending: the `/p/{slug}` badge chip UI (P1 above) — once shipped, apply the same reordering there — a profile that shows supporter badges and favorite counts sells belonging before it sells cents-per-view. Change the primary CTA on public profiles to something identity/belonging-flavored ("Follow [Candidate] · See who else in [District] is watching"), with the earn-per-view mechanic as a secondary line.
- **Channel 2 (Early-bank referral loop)** — Keep the commission mechanics (they're real growth infrastructure), but change the share assets: instead of "I made $X this week," templates built around "I got my block talking about the school board race" / group-progress screenshots ("Our coalition hit 60% of its signature goal"). This also is more FTC-safe than dollar-amount testimonials.
- **Channel 3 (Paid acquisition)** — Current targeting language is *"earn money watching political ads."* Test a parallel creative track around civic identity/community ("Join your neighborhood's voice," "Know where your block stands before election day") against the existing money-led creative, and measure retention (not just CAC) by cohort — the hypothesis from the overjustification research is that identity-recruited users will have materially better 30/60-day retention even if initial CAC is comparable or slightly higher.
- **Channel 4 (Advertiser supply)** — Largely unaffected; politicians/citizens/groups are paying for reach, not identity, so this channel's money-forward pitch to advertisers is appropriate as-is. Only change: pitch groups (once shipped) as a distribution channel to advertisers — "reach an organized, engaged coalition," not just a zip code.

### New success metrics, alongside (not replacing) the financial ones

`MARKETING_FINANCIAL_GOALS.md`'s KPI table is all money/volume. Add a parallel set that measures whether the pillars are actually landing:

| KPI | What it tells you |
|---|---|
| **Trackable now:** % of active voters with ≥1 self-declared badge | Self-identity adoption — measurable starting today |
| **Trackable now:** % of self-declared badges flipped public vs. left private | Comfort level with civic-identity exposure — a low public rate isn't a failure, it's the privacy control working as designed; watch the *trend*, not the absolute number |
| % of active voters in a Neighborhood Group | Belonging adoption (post-Sprint 8.5) |
| % of active voters with ≥1 note written | Meaning-making adoption |
| 30-day retention: group members vs. non-members | Direct test of whether belonging drives retention beyond payout alone |
| 30-day retention: badge-holders vs. non-badge-holders | Same test, available now — don't wait for Groups to start measuring identity's effect on retention |
| 30-day retention: identity-led acquisition cohort vs. money-led acquisition cohort | Direct test of the overjustification hypothesis — run as an A/B on Channel 3 |
| Outcome-tracking open/click rate (once shipped) | Whether the meaning-making feedback loop actually gets used |

If group members and badge-holders retain meaningfully better than payout-only users, that's the evidence to lead the next fundraising/marketing cycle with — a much stronger, more defensible story than "we pay more per view."

---

## Sequencing summary

1. ✅ **Shipped (2026-07-28):** Badge/favorite visibility UI (self-identity P0) + welcome page reframe. Took more than the original estimate — the self-declare UI didn't exist at all, not just the toggle — see the audit-before-scoping lesson above.
2. ✅ **Shipped (2026-07-29):** Neighborhood Groups MVP — membership + public group page (belonging P0). The audit-before-scoping lesson from Phase 1 held again: Creative.md §8.5 was schema/services-only, no views, so this was a from-zero UI build, not wiring.
3. ✅ **Shipped (2026-07-29):** "People near you" counts on Causes/Ballot Measures (belonging P0) — aggregate counts only, per-person visibility left as a future opt-in, per the self-asserted-vs-system-computed test above.
4. ✅ **Shipped (2026-07-29), not originally scoped:** Group member management (roles, promote/demote, remove) and group-scoped events — both surfaced as necessary once Groups MVP existed; a group with members nobody could see or manage, and nothing to organize around, wasn't a complete belonging surface. Reuses the existing `CivicEvent` polymorphic host relation rather than a new events table.
5. ✅ **Shipped (2026-07-29), not originally scoped:** Guest Trial Mode (meaning-making) — admin-toggled anonymous-visitor trial that reaches the meaning/belonging surfaces above with no signup wall, while keeping every money route blocked. Placed under meaning-making rather than acquisition/marketing because its purpose is experiential (let the feedback loop be felt before it's paid for), not funnel mechanics.
6. **Next:** Outcome tracking + civic year-in-review (meaning-making P0) — pure read-model work over data already captured (`voter_politician_notes`, `voter_favorite_causes`, view history), independent of everything above and can be built concurrently with whatever's next on belonging.
7. **After:** Group badges/themes (Creative.md §8.5–8.6), now unblocked by member management + events shipping, and marketing's identity-led creative track, so the ads have something real to point to. The `/p/{slug}` badge chip UI (self-identity P1) is a smaller, independent item that could also slot in here or earlier — it's not blocked by anything above.

This keeps the existing per-view economics and `MARKETING_FINANCIAL_GOALS.md` funnel intact underneath — nothing here removes the payout. It reorders what gets built next and what gets said first, on the bet (testable now via the badge-holder retention KPI above, no need to wait for Groups) that belonging, meaning, and identity are what keep a voter here in month three, after the novelty of $0.50/view has worn off.
