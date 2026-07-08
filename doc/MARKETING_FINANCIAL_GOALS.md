# U9itus — Marketing & Financial Goals
_Prepared: 2026-07-06_

---

## Platform Summary

U9itus is a political advertising marketplace and civic transparency platform.
Politicians, campaigns, citizens, and ballot measure advocates **pay $1.00 per view**
to reach voters. Voters **earn $0.50 per view** they complete. Early-bank.com is the
referral front-end: members pay a **one-time $20 fee** and earn $10 per voter they
recruit plus 10% of each recruit's viewing and Ad Spend (Politician) revenue (weekly Stripe payouts). 

---

## Revenue Streams

| # | Stream | Unit | Rate |
|---|---|---|---|
| 1 | Politician/citizen ad views | per view | $1.00 charged |
| 2 | Head Enterprises platform fee | 15% of ad revenue | $0.15/view |
| 3 | Early-bank membership | one-time | $20.00/member |
| 4 | Early-bank referral spread | per new member | ~$10 net (after $10 commission) |
| 5 | Stripe gross-up fee | 2.5% on top-ups | ~$0.025/view equivalent |
| 6 | Procurement commissions | 10% one-time | $0.06/view amortized |

---

## Per-View Unit Economics

```
Politician pays:                  $1.00
Less voter payout:               −$0.50
Less referral commission (10%):  −$0.05
                                 ──────
Platform gross spread:            $0.45

Less Head Enterprises fee (15%): −$0.15
Less Stripe processing (~2.5%):  −$0.025
                                 ──────
Net platform margin per view:    ~$0.275
```

---

## Growth Target — 3,600 Users/Day

### Early-bank Funnel (3% conversion to $20 fee)

| Metric | Daily | Monthly |
|---|---|---|
| New user registrations | 3,600 | 108,000 |
| Early-bank buyers @ 3% | 108 | 3,240 |
| Gross Early-bank revenue | $2,160 | $64,800 |
| Less $10 referral commission | −$1,080 | −$32,400 |
| Less Stripe processing (~2.9%+$0.30) | ~−$95 | ~−$2,844 |
| **Net Early-bank revenue** | **~$985** | **~$29,556** |

### Ad View Revenue (10 views/voter/day average)

| Metric | Daily | Monthly |
|---|---|---|
| Cumulative active voters (30-day base) | 108,000 | — |
| Views/day @ 10 avg | 1,080,000 | — |
| Gross ad revenue ($1.00/view) | $1,080,000 | — |
| Voter payouts ($0.50/view) | −$540,000 | — |
| Referral commissions ($0.05/view) | −$54,000 | — |
| HE platform fee ($0.15/view) | −$162,000 | — |
| Stripe processing (~$0.025/view) | −$27,000 | — |
| **Net ad margin (~$0.275/view)** | **~$297,000** | **~$8.9M** |

> ⚠️ **Ad revenue requires equivalent politician/advertiser spend.** At 1,080,000 views/day
> you need $1.08M/day of committed ad budgets. The realistic early-stage number is
> 10–50 active campaigns × 1,000–5,000 views/day = 10,000–250,000 views/day.
> Scale ad supply and voter demand together.

### Conservative Ramp Projections

| Month | Daily Users | Daily Views | Daily Ad Net | Daily EB Net | Daily Total |
|---|---|---|---|---|---|
| M1 (launch) | 100 | 500 | $138 | $27 | ~$165 |
| M3 | 400 | 4,000 | $1,100 | $110 | ~$1,210 |
| M6 | 1,200 | 24,000 | $6,600 | $330 | ~$6,930 |
| M12 | 3,600 | 108,000 | $29,700 | $985 | ~$30,685 |

---

## Early-bank Member Earnings (what you can market)

| Direct Referrals | Referral Earnings | Recruits' Viewing Revenue | Your 10% | Weekly Total |
|---|---|---|---|---|
| 1 | $10 | $25 (50 views @ $0.50) | $2.50 | $12.50 |
| 3 | $30 | $75 | $7.50 | $37.50 |
| 5 | $50 | $125 | $12.50 | $62.50 |
| 10 | $100 | $250 | $25.00 | $125.00 |
| 20 | $200 | $500 | $50.00 | $250.00 |

> Assumes each direct recruit completes 50 views/week at $0.50/view.
> Platform fraud cap: max 50 views/voter/day (350/week maximum).

---

## Marketing Channels & CAC Targets

### Channel 1 — Programmatic SEO (Zero CAC)
**What:** One SEO page per candidate/race/state using existing transparency data
(OpenSecrets, FEC, Ballotpedia, Vote Smart, Google Civic, Congress.gov).
Already built: `/district-lookup`, `/politicians`, `/p/{slug}`, `/p/{slug}/campaigns`.

**Action items:**
- Add JSON-LD schema.org `Person` + `Election` structured data to every public profile
- Generate XML sitemaps for all politician + candidate pages
- Optimize Core Web Vitals (Lighthouse score target ≥ 90)
- Add "Earn $0.50 to watch this candidate's message" CTA on every public profile
- `MAP_SIGN_IN_CTA=true` flag already exists — activate it

**Target:** 500–2,000 organic signups/day at election-cycle peak (no ad spend).

---

### Channel 2 — Early-bank Referral Loop (Self-Funding)
**What:** Each $20 buyer is a recruiter. At viral coefficient K=0.5, every 2 buyers
bring 1 more buyer organically.

**Action items:**
1. Ship member referral link + QR code generation (P6 Early-bank expansion)
2. Weekly payout email with earnings dashboard link (trust driver)
3. In-product leaderboard showing top recruiters
4. Share assets: pre-written social captions, Instagram stories templates
5. Cookie attribution already built (`earlybank_member_id` capture on registration)

**Target:** K ≥ 0.3 to meaningfully reduce paid CAC.

---

### Channel 3 — Paid Acquisition (Fill the Gap)
**What:** Meta / TikTok / YouTube targeting "earn money watching political ads" + civic interest.

**Targeting:** US adults 25–55, civic/political interest, income-opportunity seekers.

| CAC Target | Budget at 100 buyers/day | Budget at 1,000 buyers/day |
|---|---|---|
| $3 CAC | $300/day | $3,000/day |
| $5 CAC | $500/day | $5,000/day |
| $8 CAC | $800/day | $8,000/day |

**Break-even CAC calculation:**
- Early-bank net per buyer: ~$9.85 (after $10 commission + Stripe)
- Voter lifetime view-earnings (avg 50 views/week × 4 weeks × $0.275 net): ~$55 LTV
- Max sustainable CAC: ~$55 (LTV-based) — well above the $3–8 range.

**Measurement:** GA4 + GTM + Google Ads conversion tags already live via `InjectAnalyticsTags` middleware. Measure: signup → verified voter → $20 purchase → first view.

---

### Channel 4 — Advertiser Supply Development (Politicians, Citizens & Groups)
**Critical:** Without spending advertisers, voters churn immediately. Run in parallel with voter acquisition from day one.

#### Politician / ballot measure outbound
- Local campaigns: city council, school board, county supervisor, judicial races
- Ballot measure committees (Yes/No propositions and local ordinances)
- PACs and issue advocacy orgs
- Pitch: *"Reach verified voters in your district who watch the full message — guaranteed 100% completion, verified real person, no bots."*

#### Citizen advertiser — lowest friction, highest long-tail volume
- Small businesses: workshops, grand openings, local services, startups recruiting beta users
- Nonprofits collecting signatures or building petition drives
- Community organizers forming neighborhood groups (also seeds Sprint 8.5 groups)
- Pitch: *"Reach 500 verified local voters for $50. Your ad plays only to people in your zip code who watch the whole thing. No bots, no skip buttons."*
- Self-serve checkout, no sales call required — this is your off-cycle inventory that keeps voters earning between elections

#### Group campaigns (Sprint 8.5)
- Neighborhood groups pool Patreon-style backer contributions into a shared ad budget
- Each group campaign is a citizen campaign funded collectively
- Early-bank members can recruit group backers (P6 gap — advertiser referral commission not yet implemented; currently `REFERRAL_COMMISSION_PERCENT=10` only fires on viewer earnings, not advertiser spend)

#### Minimum viable inventory
10 active campaigns × 2,000 views/day = 20,000 views/day
(enough for voters to earn ~$50/week at $0.50/view — above the churn threshold)

#### Introductory pricing
Consider waiving the first campaign's 15% platform fee for citizen advertisers to seed inventory. The voter payout economics still hold and you gain the supply flywheel.

---

## 90-Day Action Plan

### Days 1–30: Foundation
- [ ] Confirm $1.00/$0.50 pricing across `.env`, PlatformSettings, and Early-bank marketing copy (currently inconsistent — `.env` local has $0.25 but config default is $0.50)
- [ ] Build P2 WalletService (integer-cent ledger) — required for audit trail at scale
- [ ] Generate sitemaps for all public politician/candidate pages
- [ ] Add JSON-LD structured data to `/p/{slug}` profile pages
- [ ] Activate `MAP_SIGN_IN_CTA=true` on all public profiles
- [ ] Run first 10 politicians/campaigns through full onboarding manually — document friction points
- [ ] Set up GA4 conversion funnel: landing → register → verify → $20 purchase → first view

### Days 31–60: Referral Loop
- [ ] Ship Early-bank member link + QR code generator (P6)
- [ ] Launch weekly payout email with earnings stats
- [ ] Run first paid test: $500 on Meta targeting "earn money + political" (100–150 signups)
- [ ] Measure CAC, conversion rate to $20, and voter LTV — validate before scaling
- [ ] Onboard 5–10 local politicians/ballot campaigns for ad inventory

### Days 61–90: Scale
- [ ] If CAC < $10 and LTV > $30: increase paid budget to $2,000–5,000/day
- [ ] Ship P3 donations + memberships (adds non-view revenue per politician page visit)
- [ ] Launch leaderboard + referral share assets for Early-bank members
- [ ] Begin SEO content push — district-specific landing pages for major metros
- [ ] Target: 400–800 daily signups, 12–24 Early-bank buyers/day ($120–240/day net EB)

---

## Key Risks & Mitigations

| Risk | Severity | Mitigation |
|---|---|---|
| Voter churn if no ad inventory to watch | High | Onboard 10+ advertisers before scaling voter acquisition |
| FTC scrutiny of earnings claims ("$250/week") | High | Add disclaimer: "results not typical, based on 20 direct referrals × 50 views/week." Only use actual member data in ads once available. |
| FEC exposure for paying voters to watch political ads | High | Obtain FEC counsel before crossing 10,000 political ad views/day |
| Fraud (fake views, card testing on $20 purchase) | Medium | Fraud stack built (FraudPreventionService, DeviceFingerprintService, IpReputationService, 48hr hold, 50 views/day cap) — monitor velocity rules on Early-bank purchases |
| Pricing inconsistency ($0.25 vs $0.50 voter payout) | Medium | Reconcile immediately: pick one, update `.env`, PlatformSettings, and marketing copy |
| Supply/demand imbalance | Medium | Grow advertiser pipeline in parallel with voter acquisition |
| MeToken launch without securities counsel | High | Keep P7 counsel-gated; use internal ledger (P2) first |

---

## Success Metrics

| KPI | M1 Target | M6 Target | M12 Target |
|---|---|---|---|
| Daily new registrations | 100 | 1,200 | 3,600 |
| Daily Early-bank buyers | 3 | 36 | 108 |
| Active campaigns (advertisers) | 5 | 30 | 100 |
| Daily views completed | 500 | 24,000 | 108,000 |
| Daily net platform revenue | $165 | $6,930 | $30,685 |
| Voter 30-day retention | — | ≥ 40% | ≥ 50% |
| Early-bank referral K coefficient | — | ≥ 0.3 | ≥ 0.5 |
| Paid CAC | ≤ $8 | ≤ $6 | ≤ $5 |
