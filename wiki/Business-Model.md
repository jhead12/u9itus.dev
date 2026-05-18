# Business Model

## Core Concept

U9itus connects politicians directly with voters through paid video messages. Politicians pay to reach voters; voters earn money for watching.

## Per-View Economics

| Component | Amount |
|-----------|--------|
| Politician pays per view | **$0.60** |
| Voter earns per view | **$0.25** |
| Voter-referral commission (10% of voter payout) | $0.025 per view _(recurring)_ |
| Politician-procurement commission (10% of 1st buy) | ~$0.06+ _(one-time)_ |
| Payment processing (Stripe 2.5% gross-up) | 2.5% of credit top-up amount |
| Ops & infrastructure | ~$0.03–$0.12 |
| **Platform net profit** | **$0.18–$0.30 (30–50% margin)** |

## Stripe Fee Handling

When a politician adds credits, the charge is grossed-up so the platform always receives the full requested credit value. For example:

- Politician requests **$100.00** in credits
- Stripe charges **$102.56** (grossed-up by 2.5%)
- After Stripe deducts **$2.56**, the platform nets exactly **$100.00**
- Politician's credit balance is topped up by **$100.00**

## Referral Incentive Structure

Voters earn commissions by referring new people to the platform:

| Referral Type | Who You Refer | Commission | Frequency |
|---------------|---------------|------------|-----------|
| **Voter Referral** | A new voter | 10% of their payout ($0.025) per view | Recurring |
| **Politician Referral** | A new politician | 10% residual income (Founding Members) | Ongoing |

- Voter referral links: `/register/voter?ref=<code>`
- Politician referral links: `/register/politician?ref=<code>`
- Procurement commission fires automatically on the recruited politician's first Stripe credit purchase — triggered **only once** per politician

## Campaign Types

| Type | Description |
|------|-------------|
| Video message | Pre-recorded political ad (10–20 seconds) |
| Live feed | Real-time broadcast (Phase 12 WebRTC) |
| Q&A campaign | Interactive Q&A format (coming soon) |

## Supported Video Sources

| Source | Status |
|--------|--------|
| YouTube (`YT.Player`) | ✅ Implemented |
| Vimeo (Vimeo Player SDK) | ✅ Implemented |
| Direct file (MP4, WebM, OGG) | ✅ Implemented |
| AWS S3 / CloudFront | ✅ Implemented |
| HLS live streams (`hls.js`) | ✅ Implemented |
| Wistia | 🔜 Planned |
| Cloudflare Stream | 🔜 Planned |

## Political Targeting

Politicians can target voters by:

- Governance level: Federal, State, County, City, School Board, Special District
- Office type: Mayor, City Council, Governor, US Senator, etc.
- Geography: State, city, congressional district

## Watch Requirements

- Voters must watch **100%** of the video to earn the payout
- Anti-skip enforcement prevents seeking forward
- Server-side heartbeat tracks progress every 5 seconds
- Completion event triggers payout + post-view survey

---

← [Architecture](Architecture.md) | [User Roles →](User-Roles.md)
