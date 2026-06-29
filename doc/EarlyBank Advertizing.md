## Description
Hello Josh, and Jonathan, please check out a promotional system that I have devised for recruiting subscribers into the U9itus.com marketing system using the old EarlyBank process used in the old Earlybank system that were very effective. 

I have adopted AI to provide an earnings chart to illustrate the earning potential of the program. Perhaps we should talk about this, as I realize that there will be more work invovled, but the program is very lucrative, indeed. Check it out below:

Sign in to the Early-bank.com income-earning system. Here, you can earn referral income by inviting other members to join our U9itus.com program as you promote your products, goods, services, and ideas to people interested in purchasing them.

Here is what you will receive:

For a one-time $20.00** subscription fee, you can earn **$10.00 for each member you refer directly into our Head Enterprises U9itus.com promotional system. You will be provided with a membership link, QR code, and other tools to promote the U9itus.com system not only to politicians and voters, but to other citizens who have a grievance to air or a product to market.

Our unique promotional system allows members to post video messages into our messaging system. These videos will be viewed by program members or other individuals you direct to market your product, at a cost of $1.00 per viewer**. Viewers will be paid **$0.50 to watch your advertisement.

You will also have the opportunity to recruit other U9itus.com members into our system and receive 10% of the revenue they earn from viewing promotions. Additionally, you may earn income by recruiting politicians, merchants, and others who wish to promote their products, goods, services, and ideas through this new and exciting marketing system.

Please note: Our promotional system is not multilevel. Member subscribers are paid exclusively for direct referrals. However, subscribers who recruit politicians, merchants, and others who constantly use the U9itus.com marketing system will receive revenue from repeat purchases made through the U9itus.com Promoting system. We will use cookies to assure that you are paid for your efforts.

Subscribers will be paid weekly for their referring and recruiting efforts.

Example Earnings Chart (Weekly Projection)
The chart below assumes you have paid the one-time $20 subscription fee and shows potential weekly earnings based on direct referrals and passive income from recruits' viewing activity.

Direct Referrals (Active)	Referral Earnings ($10 each)	Recruits' Viewing Revenue	Your 10% Share	Total Weekly Earnings
0	$0	$0	$0	$0
1	$10	$50	$5	$15
3	$30	$150	$15	$45
5	$50	$250	$25	$75
10	$100	$500	$50	$150
20	$200	$1,000	$100	$300
Chart Assumptions:

Referral Earnings: $10 per new, active direct referral.

Recruits' Viewing Revenue: Each direct recruit earns $50 per week from viewing promotions (for example, viewing 100 ads at $0.50 each).

Your 10% Share: You receive 10% of each recruit's weekly viewing revenue.

Repeat Purchases: Revenue from politicians/merchants is not included in this chart, as it varies by their usage frequency.

---

**Submitted by:** johnh62@gmail.com

**Submitted at:** Fri Jun 26 2026 10:29:06 GMT-0700 (Pacific Daylight Time)

*Submitted via Un9itus Feedback & Bug Report Form*




___________________

Is U9itus a viable product?
Short answer: The transparency layer is clearly viable and undervalued. The pay-voters-to-watch-political-ads core has real legal and market risk that needs to be de-risked before scaling.
What's genuinely strong
Transparency aggregator is a real moat. Chaining OpenSecrets + FEC + Ballotpedia + Vote Smart + Google Civic + Congress.gov into a single matched record (LocalCandidateAggregator + CandidateIdentityLink with match scoring) is hard, useful, and no one owns this UX well for the average voter.
District lookup is a proven traffic magnet. Ballotpedia and Vote411 prove the demand. You already have it.
Unit economics math works if you can fill the inventory. $0.18–$0.30 net per view at 30–50% margin is healthy.
Anti-fraud baseline is serious for an MVP — token delivery, 100% watch, heartbeat, device fingerprint, 48-hr hold, KYC. Most ad startups skip this.
Stripe Connect plumbing is already hardened (lock + transaction on Express account creation, bi-directional is_verified mirroring).
Where viability is fragile
1. Legal risk on paying voters (the biggest one)
Paying people to consume political content during election windows sits uncomfortably close to:

52 U.S.C. § 10307(c) — federal prohibition on paying for voting (not viewing, but optics matter and Musk's $1M lottery just got litigated).
State election codes — many criminalize "anything of value" tied to political activity; definitions vary.
FEC in-kind contribution — if a politician pays the platform and a voter receives cash to watch their ad, a regulator could argue the voter received an in-kind benefit from the campaign.
FTC endorsement rules if voters share/refer.

Mitigation: Get an election-law opinion letter before scaling. Consider reframing payment as "civic research stipend" decoupled from any single candidate, or pay in non-cash credits (charitable donation, gift card pool) in higher-risk states. Geo-fence states where it's clearly prohibited.
2. Two-sided cold-start
Voters won't show up for empty inventory; politicians won't pay $0.60/view to a small audience. Classic chicken-and-egg.

Mitigation: Lead with the free transparency product (district lookup + candidate profiles). Build the voter audience on civic utility, then layer paid views on top.
3. Politician willingness to pay $0.60/CPV
That's a ~$600 CPM. Premium YouTube political CPMs are $20–$50. You're 10–30× more expensive on the headline number — justified only if you can prove 100% completion + verified human + targeted district is worth it. Need a case study.

Mitigation: Offer down-ballot races (school board, city council, state legislature) where YouTube/Meta targeting is useless and your district-precision is genuinely differentiated. That's where price tolerance is highest.
4. Content moderation & brand risk
You're hosting political video at scale. One deepfake, one defamation suit, one foreign-money campaign and you're in headlines.

Mitigation: Mandatory government-email verification for politicians (you have a government_email_verification field already — enforce it), source-of-funds disclosure on top-ups, public ad library (à la Meta's), human review queue before first campaign goes live.
5. Fraud will adapt
Token + KYC + 100% watch is great today. Tomorrow it's headless Chrome + SIM-farm KYC + ML-driven mouse movement.

Mitigation: Add proof-of-humanity check at watch time (random captcha mid-video, gaze/eye-tracking on mobile, or Worldcoin-style attestation). Build a fraud-rate dashboard now so you can measure regressions.
6. Voter LTV vs CAC
At $0.25/view × maybe 10 views/month = $2.50/mo gross to voter. Referral commission is $0.025/view recurring. CAC via paid acquisition will exceed LTV fast unless organic growth from district-lookup dominates.

Mitigation: Treat the free transparency tool as the acquisition engine. Paid viewing is monetization, not acquisition.
Concrete improvements, ranked by ROI
Tier 1 — do now (low cost, high leverage)
Lead with transparency, not earnings. Reposition home page: "See who funds your candidates." Earnings is a secondary CTA. This sidesteps legal optics and broadens TAM beyond people motivated by $2/month.
Public ad library. Every paid campaign is visible with spend + targeting + completion stats. Defuses regulatory and PR risk; doubles as marketing.
Government email enforcement for politicians before they can spend a dollar. You already have the field.
State-by-state legal map baked into the platform — disable voter payouts in restrictive states automatically. Cheap insurance.
Down-ballot focus. Pivot sales to the school board, city council, and 3state house races. That's where your district precision is unique.
Tier 2 — next quarter
News scraping per candidate (already in your stated roadmap). Local news + their public statements + their voting record on the same page = "voter's research dashboard."
Post-view quiz instead of just watch-to-earn. "What was this politician's stance on X?" Pays only if answered correctly. Massively reduces background-tab fraud and reframes the payment as an educational stipend, which is legally safer.
Proof-of-human at view time. Random in-video captcha or biometric check on mobile.
Native mobile app. You have android and ios folders — push notification + watch flow is far better in-app than web. Token delivery → push → in-app watch is the strongest fraud and UX story.
Comparative candidate view. Side-by-side donors, voting record, positions for all candidates in a race. Killer feature for the district-lookup page.
Tier 3 — strategic
Pivot positioning to "Civic Research Platform with Optional Earnings." Sell to civic foundations, libraries, journalism nonprofits as a free transparency tool; monetize the political-ad side separately. Different brand, different risk profile, shared data layer.
B2B revenue line: sell the aggregated, matched candidate dataset (via API) to journalists, campaigns, academic researchers. That data layer is genuinely hard to build and recurring SaaS revenue would dwarf per-view economics at small scale.
Issue-based campaigns, not just candidate-based. Let advocacy orgs (not just politicians) pay for verified views. Bigger TAM, less election-law exposure.
Down-ballot voter guide partnerships with local newspapers / League of Women Voters chapters. Embeddable widget powered by your aggregator.
My honest take
The product as currently framed ("Politicians Pay, Voters Earn") is viable but legally exposed and hard to scale. The underlying assets (the aggregator, district lookup, candidate matching, fraud-resistant view system) are genuinely valuable and underexploited.

The highest-EV move is to reframe the product: lead with transparency and civic utility (free, growth engine), monetize through down-ballot political ads + B2B data + issue advocacy rather than top-of-ticket federal races, and treat voter payouts as a feature in legally-cleared states rather than the headline value prop. That repositioning keeps every line of code you've already shipped and dramatically expands your TAM and lowers regulatory beta.
