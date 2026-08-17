# Implemented Features — August 2026 Session Log

Compiled from the Obsidian vault (`doc/vault/u9itus/Decisions Journal/` and `Repo Architecture/`). Covers features and fixes actually shipped, not open ideas (see `Feature Ideas/` in the vault for those).

## Map

- **U.S. Senators in the statewide panel** — Senators now appear in the map's "Statewide & Federal Offices" list instead of being silently dropped (they have no congressional district, so the old House-by-district bucket never matched them). Statewide officeholders' drawer now also shows state population and term-end date in place of blank stats.
- **Working House/Senate candidate scraper** — replaced a scraper strategy that had been silently returning zero results for over a month (hitting a 404'd Ballotpedia index page) with one that builds direct per-district race URLs. Added WAF-202 detection, retries, and a circuit breaker after repeated failures.
- **District share links** — the district drawer's Share button builds a `/map?state&district&slug&ref=` URL using the existing deep-link params, so a shared link lands the recipient on the exact district and attributes the referral.
- **Guest map favorites** — visitors who aren't logged in can now save/favorite districts and cities via a cookie (25-item cap, 180-day TTL), which auto-merges into their real account on login/registration.
- **Weekly "saved places" digest email** — voters and guests can opt in to a weekly email summarizing news/endorsements for the districts/cities they've favorited. Guests get a lightweight, double-opt-in email capture without creating a full account.
- **More Vote Smart data surfaced on the map drawer** — "Recent Votes" (curated key votes) now shown on the Overview tab, reusing data that was already being fetched and cached but discarded.

## Public Profile Page

- **FEC Cash-on-Hand/Debt fix** — these fields now fall back to OpenFEC's "last filing" balance fields when the primary cycle-end fields are blank, fixing candidates who showed real fundraising totals but a false $0 cash-on-hand/debt.
- **Independent Spending committee names** — fixed committee names silently regressing to raw FEC IDs on enrichment failures; added a durable `committees` registry table so a name resolved once is reused for every candidate, instead of re-resolving (and potentially failing) per profile.
- **Clickable FEC committee IDs** — every committee ID in the Independent Spending list now links out (Google search) for context, separate from the committee-name link to its FEC.gov page.
- **Ballotpedia "Dig Deeper" card rebuilt** — the card was calling a Ballotpedia API that doesn't exist; rebuilt to scrape the politician's actual Ballotpedia wiki article for a bio excerpt and committee assignments.
- **Birthdate/age on profile hero** — "🎂 Born {date} · Age {age}" now shown, reusing Vote Smart bio data that was already fetched but previously discarded.
- **ADA accessibility pass** — fixed low-contrast text, added a visible focus ring to the referral-link input, made tooltip info-icons keyboard-accessible, and added tooltips explaining FEC/OpenSecrets terms (Total Raised, Cash on Hand, Debt Owed, Support/Oppose).
- **Self-endorsement badge fix** — politicians no longer show a badge implying they endorsed themselves for their own seat; the endorsement detector now checks whether the "endorser" name it captured is actually the politician's own name.
- **Wikipedia link backfill** — added Wikipedia links for Maine politicians missing them, using the existing lookup service (manually vetted to avoid matching junk/non-person rows in the data).

## Blog / Posts

- **Rich text editor + image uploads** — Quill-based editor (headings, formatting, lists, links, inline images) with real featured-image and inline-image upload, replacing the previous plain-text setup.
- **WordPress-style two-column post editor layout** — main content column plus a sidebar (Publish, Featured Image, Topics), using the HTML5 `form=` attribute so sidebar fields submit with the main form despite Publish/Archive being separate endpoints.
- **Real HTML sanitizer** — replaced a hand-rolled regex/strip_tags sanitizer with a proper DOM-based sanitizer (`symfony/html-sanitizer`), closing off a class of bypass regex sanitizers are prone to.
- **Atomic post promotion** — credit deduction and marking a post promoted now happen in one transaction, so a crash mid-promotion can't charge a wallet without actually promoting the post.
- **Autosave** — the editor autosaves every 20 seconds in the background (plus a best-effort save on tab close), with all slug-dependent URLs on the page patched live if the title change regenerates the post's slug mid-session.
- **Rich embeds** — pasting a YouTube, Instagram, SoundCloud, X, or TikTok link now renders a real embed in the post body, server-validated and server-templated rather than trusting client HTML.
- **Wix/Word paste cleanup** — stripped the blank-paragraph spacer blocks and inline colors/fonts that pasting from Wix or Word used to leave behind, both live in the editor and on save.
- **Share buttons** on public post pages, reusing the existing referral-share partial.
- **Fixed invisible post body text** on production (missing Tailwind Typography plugin) and **silent image upload failures** (a failed storage write was being treated as success).
- **Playwright E2E test harness** set up from scratch, with a real browser test covering the Wix-paste cleanup end-to-end.

## Referrals / Early-bank

- **Early-bank bonus display hidden** until the bonus program's terms and payout reconciliation are finalized, since the live bonus total could show numbers contradicting what Early-bank itself had already paid out.

## Data / Infra

- Documented Railway deployment behavior (push-to-`master` deploys straight to production, no staging gate) and the two structurally different referral-attribution systems (internal referral codes vs. Early-bank member UUIDs) that are easy to conflate.
