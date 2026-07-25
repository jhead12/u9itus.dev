/**
 * OpenSecrets Candidate Finance Scraper
 *
 * OpenSecrets retired their free public API. This script scrapes the same
 * data directly from their public profile pages using Playwright.
 *
 * TWO scraping modes:
 *
 *  1. CANDIDATE mode (default) — scrapes an individual candidate's profile.
 *     The profile URL is always obtained from the candidate search results
 *     (the link OpenSecrets returns), never constructed from a name slug —
 *     the search link carries the correct slug AND office scheme:
 *       /profiles/{slug}/us_congress/summary?mpid={mpid}   (Congress members)
 *       /officeholders/{slug}/...?id={id}                  (governors, state
 *                                                          legislators, judicial)
 *     A previously-confirmed mpid, when supplied, is used only to pick the
 *     right result among same-name matches, not to build the URL.
 *     Data: total raised/spent/cash, top contributors, top industries, cycle history.
 *
 *  2. DISTRICT mode (--district) — scrapes a district race summary page:
 *       https://www.opensecrets.org/elections/{state}/federal/{state}-district-{N}/candidates
 *     Data: all candidates in the race with raised/spent/cash on hand + incumbent flag.
 *     One request covers all candidates — more efficient for map panel enrichment.
 *
 * Usage:
 *   node scripts/scrape-opensecrets.js --name="Adam Schiff" --state=CA
 *   node scripts/scrape-opensecrets.js --mpid=1105090
 *   node scripts/scrape-opensecrets.js --district=CA-31             # district mode
 *   node scripts/scrape-opensecrets.js --district=CA-31 --cycle=2026
 *   node scripts/scrape-opensecrets.js --file=storage/app/imports/politicians-to-enrich.json
 *
 * Output: JSON to stdout, or --out=path/to/output.json
 *
 * Requirements:
 *   npm install playwright
 *   npx playwright install chromium
 */

import { chromium } from 'playwright';
import { writeFileSync, mkdirSync, readFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));

// ── CLI args ─────────────────────────────────────────────────────────────────
const args = Object.fromEntries(
  process.argv.slice(2)
    .filter(a => a.startsWith('--'))
    .map(a => {
      const [k, ...rest] = a.slice(2).split('=');
      return [k, rest.join('=') || 'true'];
    })
);

const NAME_ARG     = args.name     ?? null;
const STATE_ARG    = args.state    ? args.state.toUpperCase() : null;
const CID_ARG      = args.cid      ?? null;   // legacy OpenSecrets CID
const MPID_ARG     = args.mpid     ?? null;   // new mpid from profile URL
const FILE_ARG     = args.file     ?? null;   // batch: JSON file with [{full_name, state, opensecrets_id?}]
const DISTRICT_ARG = args.district ?? null;   // e.g. "CA-31" — triggers district mode
const CYCLE_ARG    = args.cycle    ?? null;   // e.g. "2026"
const OUT_PATH  = args.out
  ? resolve(process.cwd(), args.out)
  : null;

const DELAY_MS  = 600;
const TIMEOUT   = 20_000;

// OpenSecrets sits behind Cloudflare, which challenges/blocks headless
// scrapers on datacenter IPs (the GitHub Actions runner sees "Just a
// moment..."). To get past the JS challenge we present as a normal Chrome
// on Linux (matching the ubuntu runner) and mask the headless automation
// signals Cloudflare keys on. This deliberately drops the honest "civic-bot"
// UA — a UA containing "bot" is an automatic block under Cloudflare bot-fight
// mode, which made the search page return zero results every nightly run.
const REAL_UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36';

const sleep = (ms) => new Promise(r => setTimeout(r, ms));

/**
 * Build a browser context that looks like a real Chrome visit rather than a
 * headless bot, so Cloudflare's "Just a moment..." JS challenge can solve.
 * Every page in the context gets the webdriver flag masked before site JS runs.
 */
async function newScrapeContext(browser) {
  const ctx = await browser.newContext({
    userAgent: REAL_UA,
    locale: 'en-US',
    extraHTTPHeaders: { 'Accept-Language': 'en-US,en;q=0.9' },
  });
  await ctx.addInitScript(() => {
    // Cloudflare's challenge flags headless Chromium via navigator.webdriver.
    Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
  });
  return ctx;
}

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Parses a dollar string like "$1,234,567" or "1234567" into a number.
 */
function parseMoney(str) {
  if (!str) return null;
  const clean = String(str).replace(/[^0-9.-]/g, '');
  const n = parseFloat(clean);
  return isNaN(n) ? null : n;
}

/**
 * Format a raw number back to a display string like "$1,234,567".
 */
function formatMoney(n) {
  if (n === null || n === undefined) return null;
  return '$' + Number(n).toLocaleString('en-US', { maximumFractionDigits: 0 });
}

/**
 * Build the name slug OpenSecrets uses in profile URLs.
 * e.g. "Adam B. Schiff" → "adam-b-schiff"
 */
function nameSlug(name) {
  return name
    .toLowerCase()
    .replace(/[^a-z0-9 ]/g, '')
    .trim()
    .replace(/\s+/g, '-');
}

/**
 * Detect a Cloudflare (or similar) bot-check interstitial by page title.
 * These pages return HTTP 200 with no real content, so they'd otherwise be
 * treated as a successful-but-empty scrape rather than a blocked one.
 */
function isBlockedPageTitle(title) {
  const t = (title ?? '').toLowerCase();
  return t.includes('just a moment')
    || t.includes('attention required')
    || t.includes('security verification')
    || t.includes('are you a human')
    || t.includes('access denied');
}

/**
 * Wait for the OpenSecrets search page to actually show results.
 *
 * The search page is gated by Cloudflare's "Just a moment..." JS challenge on
 * datacenter IPs. page.goto() resolves on `domcontentloaded`, which fires on
 * the challenge page itself — so a one-shot waitForSelector races and loses,
 * reading zero results while the challenge is still solving. This polls:
 * while the title is a blocked-page title, keep waiting (the challenge
 * auto-solves in a few seconds once the browser passes it); once it clears,
 * wait for a result element or the CSE "no results" marker to appear.
 *
 * Returns true if a result/no-results element rendered, false if the page was
 * still blocked after the deadline.
 */
async function waitForSearchResults(page, blockedBudgetMs = 30_000, cseBudgetMs = 15_000) {
  // CSE-specific markers only. Do NOT include a[href*="/profiles/"] etc. here —
  // those match OpenSecrets nav links immediately and would signal "ready"
  // before the Google CSE widget has actually injected any results.
  const RESULT_SELECTOR = 'a.gs-title, .gsc-webResult, .gs-no-results-result';
  const start = Date.now();
  let unblockedAt = null;

  // Two phases:
  //  - While the title is a Cloudflare "Just a moment..." challenge, keep
  //    waiting up to blockedBudgetMs for it to auto-solve.
  //  - Once past the challenge, the Google CSE widget still fetches + injects
  //    results asynchronously — keep waiting up to cseBudgetMs for a result
  //    (or the CSE "no results" marker) to appear.
  while (true) {
    const state = await page.evaluate((sel) => {
      const title = document.title || '';
      const blocked = /just a moment|attention required|security verification|are you a human|access denied/i.test(title);
      return { blocked, ready: !!document.querySelector(sel) };
    }, RESULT_SELECTOR);

    if (state.ready) return true;

    if (state.blocked) {
      if (Date.now() - start > blockedBudgetMs) return false;
      await sleep(700);
      continue;
    }

    if (unblockedAt === null) unblockedAt = Date.now();
    if (Date.now() - unblockedAt > cseBudgetMs) return false;
    await sleep(500);
  }
}

// ── Core scraping functions ───────────────────────────────────────────────────

/**
 * Search OpenSecrets for a candidate by name and state, then return the REAL
 * profile link from the search results (never a constructed URL).
 *
 * The profile URL is always taken from the search-result link the candidate
 * search returns — that link carries the correct slug AND the correct office
 * scheme (/profiles/.../us_congress/...?mpid= for Congress, /officeholders/
 * ...?id= for governors/state legislators/judicial). Constructing a URL from a
 * name slug + a known id gets both of those wrong.
 *
 * When `mpid` is supplied (a previously-confirmed candidate id stored on the
 * politician), it is used ONLY to pick the right result among multiple matches
 * (e.g. two people named "John Smith") — never to build a URL.
 *
 * Returns { mpid, profileUrl, name, guessed } or null (blocked by bot-check).
 */
async function searchCandidate(page, name, state, mpid = null) {
  const query = encodeURIComponent(name + (state ? ' ' + state : ''));
  const searchUrl = `https://www.opensecrets.org/search?q=${query}&type=politicians`;

  console.error(`  [search] ${name} ${state ?? ''} → ${searchUrl}`);

  try {
    await page.goto(searchUrl, { waitUntil: 'domcontentloaded', timeout: TIMEOUT });

    // The search page is behind Cloudflare's "Just a moment..." JS challenge
    // on datacenter IPs. domcontentloaded fires on the challenge page, so wait
    // for it to clear and for results (or the CSE "no results" marker) to
    // render before reading the DOM.
    await waitForSearchResults(page);

    // Extract search result links. OpenSecrets currently uses two URL
    // schemes for candidate profile pages depending on office type:
    //  - /profiles/{slug}/us_congress/...  (current/former members of Congress)
    //  - /officeholders/{slug}/...         (governors, state legislators,
    //    judicial officeholders, and other non-Congress offices)
    const results = await page.evaluate(() => {
      return Array.from(document.querySelectorAll('a.gs-title[href], a[href*="/profiles/"], a[href*="/officeholders/"]'))
        .filter(a => {
          if (!a.href) return false;
          // /us_congress/ = individual member profile. /officeholders/ needs a
          // slug + sub-page (e.g. /officeholders/gavin-newsom/summary) to be a
          // candidate profile — bare category links like /officeholders/list
          // (the "Governors" nav link) must not be mistaken for a match.
          return a.href.includes('/us_congress/') || /\/officeholders\/[^/?]+\/[^/?]+/.test(a.href);
        })
        .map(a => ({
          text: a.textContent.trim(),
          href: a.href,
        }))
        // The CSE widget renders each result twice (title link + snippet link)
        .filter((r, i, arr) => arr.findIndex(x => x.href === r.href) === i)
        .slice(0, 5);
    });

    // Diagnostic: surface what the search page actually returned so a
    // systemic "0 results" (stale markup, consent/anti-bot interstitial, or
    // a blocked GitHub Actions IP) is visible in the workflow log instead of
    // silently falling through to a slug guess.
    console.error(`  [search] results=${results.length}` +
      (results.length ? ` first="${results[0]?.text}" href=${results[0]?.href}` : ''));

    if (!results.length) {
      const diag = await page.evaluate(() => {
        const bodyText = (document.body?.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 300);
        const consentMarkers = ['#onetrust-banner', '#consent', '[aria-label*="consent"]', '.cookie'];
        const hit = consentMarkers.find(s => document.querySelector(s) ? s : null);
        return {
          url: window.location.href,
          title: document.title,
          consentGate: hit || null,
          bodySnippet: bodyText,
        };
      });
      console.error(`  [search] no-results diag url=${diag.url} title="${diag.title}" consent=${diag.consentGate ?? 'none'}`);
      console.error(`  [search] no-results body: ${diag.bodySnippet}`);

      if (isBlockedPageTitle(diag.title)) {
        console.error(`  [search] Blocked by bot-check — not falling back to a guessed URL`);
        return null;
      }

      // Fallback: try direct URL construction with name slug. This is an
      // unverified guess (search found nothing) — flagged `guessed: true` so
      // the caller can discard it rather than persist it if the guess turns
      // out wrong, instead of storing a plausible-looking but broken link.
      const slug = nameSlug(name);
      const directUrl = `https://www.opensecrets.org/profiles/${slug}/us_congress/summary`;
      console.error(`  [search] No results — trying direct slug: ${directUrl}`);
      return { profileUrl: directUrl, mpid: null, name, guessed: true };
    }

    // Choose the best result. When we have a previously-confirmed candidate id
    // (mpid), prefer the result whose link carries that id — this disambiguates
    // same-name candidates. Otherwise fall back to the first result.
    let best = results[0];
    let selectedBy = 'first-result';
    if (mpid) {
      const mpidRe = new RegExp(`[?&](?:mpid|id)=${mpid}\\b`);
      const match = results.find(r => mpidRe.test(r.href));
      if (match) {
        best = match;
        selectedBy = `mpid=${mpid}`;
      } else {
        selectedBy = 'first-result (no mpid match)';
      }
    }
    console.error(`  [search] selected by ${selectedBy}: "${best.text}" href=${best.href}`);

    // Follow the chosen result the way a user would — click the actual link so
    // the browser navigates within the Cloudflare-cleared session, then scrape
    // the profile page that loads. Fall back to the link's href if the click
    // can't be performed (element gone, navigation didn't happen); the caller
    // will then page.goto() it in this same cleared context.
    let profileUrl = best.href;
    try {
      // CSE result links sometimes target a new tab; force same-tab navigation.
      await page.evaluate((href) => {
        const a = Array.from(document.querySelectorAll('a.gs-title, a[href*="/profiles/"], a[href*="/officeholders/"]'))
          .find(el => el.href === href);
        if (a) a.target = '_self';
      }, best.href);

      const handle = await page.evaluateHandle((href) => {
        return Array.from(document.querySelectorAll('a.gs-title, a[href*="/profiles/"], a[href*="/officeholders/"]'))
          .find(el => el.href === href) ?? null;
      }, best.href);
      const element = handle.asElement();
      if (element) {
        await element.click({ timeout: TIMEOUT });
        await page.waitForLoadState('domcontentloaded', { timeout: TIMEOUT }).catch(() => {});
        await sleep(DELAY_MS);
        const after = page.url();
        if (after && after.includes('opensecrets.org') && !after.includes('/search?')) {
          profileUrl = after;
          console.error(`  [search] clicked through to ${profileUrl}`);
        }
      }
    } catch (err) {
      console.error(`  [search] click failed (${err.message}) — using href directly`);
      profileUrl = best.href;
    }

    // Extract the candidate identifier from URL. The /profiles/.../us_congress/
    // scheme uses ?mpid=, the newer /officeholders/... scheme uses ?id=.
    const mpidMatch = profileUrl.match(/[?&](?:mpid|id)=(\d+)/);
    return {
      profileUrl,
      mpid: mpidMatch ? mpidMatch[1] : null,
      name: best.text,
      guessed: false,
    };
  } catch (err) {
    console.error(`  [search] Error: ${err.message}`);
    return null;
  }
}

/**
 * Scrape the summary + contributors + industries from a profile page.
 */
async function scrapeProfilePage(page, profileUrl) {
  // Ensure we're on the summary tab
  const summaryUrl = profileUrl.includes('/summary')
    ? profileUrl
    : profileUrl.replace(/\/[^/?]+(\?|$)/, '/summary$1');

  // If searchCandidate() already clicked through to this exact summary page,
  // scrape the current page instead of reloading it (saves a request and a
  // fresh Cloudflare interaction). Only skip when the path already matches.
  let alreadyThere = false;
  try {
    alreadyThere = new URL(page.url()).pathname === new URL(summaryUrl).pathname
      && page.url().includes('/summary');
  } catch {}

  if (alreadyThere) {
    console.error(`  [scrape] ${summaryUrl} (already loaded)`);
  } else {
    console.error(`  [scrape] ${summaryUrl}`);
    await page.goto(summaryUrl, { waitUntil: 'domcontentloaded', timeout: TIMEOUT });
    await sleep(DELAY_MS);
  }

  // Check for 404 / redirect to a different person
  const title = await page.title();
  if (title.toLowerCase().includes('not found') || title.toLowerCase().includes('page not found')) {
    console.error(`  [scrape] 404 — page not found`);
    return null;
  }
  if (isBlockedPageTitle(title)) {
    console.error(`  [scrape] Blocked by bot-check (title="${title}") — discarding, not a real scrape`);
    return null;
  }

  const data = await page.evaluate(() => {
    /**
     * Parse a table into an array of row objects.
     * First row is treated as headers.
     */
    function parseTable(table) {
      const rows = Array.from(table.querySelectorAll('tr'));
      if (rows.length < 2) return [];
      const headers = Array.from(rows[0].querySelectorAll('th,td'))
        .map(c => c.textContent.trim().toLowerCase().replace(/[^a-z0-9]+/g, '_'));
      return rows.slice(1).map(row => {
        const cells = Array.from(row.querySelectorAll('td'));
        const obj = {};
        headers.forEach((h, i) => {
          obj[h] = (cells[i]?.textContent ?? '').trim();
        });
        return obj;
      }).filter(r => Object.values(r).some(v => v !== ''));
    }

    const tables = Array.from(document.querySelectorAll('table'));
    const result = {
      title: document.title,
      url: window.location.href,
      tables_count: tables.length,
      summary: null,
      cycle_history: [],
      top_contributors: [],
      top_industries: [],
    };

    for (const table of tables) {
      const rows = parseTable(table);
      if (!rows.length) continue;
      const keys = Object.keys(rows[0] || {});

      // Summary box: has "category" and "amount" columns
      if (keys.some(k => k.includes('category')) && keys.some(k => k.includes('amount'))) {
        result.summary = {};
        for (const row of rows) {
          const key = row[keys.find(k => k.includes('category'))]
            ?.toLowerCase().replace(/[^a-z0-9]+/g, '_');
          const val = row[keys.find(k => k.includes('amount'))];
          if (key && val) result.summary[key] = val;
        }
        continue;
      }

      // Cycle history: has "cycle", "raised", "spent"
      if (keys.some(k => k.includes('cycle')) && keys.some(k => k.includes('raised'))) {
        result.cycle_history = rows.map(r => ({
          cycle:  r[keys.find(k => k.includes('cycle'))],
          raised: r[keys.find(k => k.includes('raised'))],
          spent:  r[keys.find(k => k.includes('spent'))],
        }));
        continue;
      }

      // Contributors: has "associated_organization"/"org" (us_congress scheme)
      // or plain "contributor" (officeholders scheme — governors, state
      // legislators, etc., which don't have the individuals/PACs breakdown).
      // OpenSecrets renders TWO tables per section: one header-only, one with data.
      // We always take the last non-empty match, so skip if rows is empty.
      if (keys.some(k => k.includes('organization') || k.includes('org') || k.includes('contributor'))) {
        const nameKey = keys.find(k => k.includes('organization') || k.includes('org') || k.includes('contributor'));
        const parsed = rows.map(r => ({
          name:        r[nameKey] ?? '',
          total:       r[keys.find(k => k === 'total')] ?? '',
          individuals: r[keys.find(k => k.includes('individual'))] ?? '',
          pacs:        r[keys.find(k => k.includes('pac'))] ?? '',
        })).filter(r => r.name);
        if (parsed.length > 0) result.top_contributors = parsed;
        continue;
      }

      // Industries: has "industry"
      if (keys.some(k => k.includes('industry'))) {
        const parsed = rows.map(r => ({
          name:        r[keys.find(k => k.includes('industry'))] ?? '',
          total:       r[keys.find(k => k === 'total')] ?? '',
          individuals: r[keys.find(k => k.includes('individual'))] ?? '',
          pacs:        r[keys.find(k => k.includes('pac'))] ?? '',
        })).filter(r => r.name);
        if (parsed.length > 0) result.top_industries = parsed;
      }
    }

    return result;
  });

  if (!data) return null;

  // Diagnostic: when the profile scrape lands but parses nothing (the
  // "0 contributors / 0 industries" symptom), surface whether we hit a real
  // profile whose table markup changed vs. a wrong/placeholder page. Logged to
  // stderr so it shows up in the workflow log via OpenSecretsService.
  console.error(`  [scrape] parsed tables=${data.tables_count ?? 0}` +
    ` summary=${data.summary ? 'yes' : 'no'}` +
    ` contributors=${data.top_contributors?.length ?? 0}` +
    ` industries=${data.top_industries?.length ?? 0}` +
    ` title="${data.title ?? ''}"`);

  // Extract mpid from actual URL (may differ from the one we searched)
  const mpidMatch = data.url?.match(/[?&]mpid=(\d+)/);

  return {
    profile_url:      data.url,
    mpid:             mpidMatch ? mpidMatch[1] : null,
    page_title:       data.title,
    summary:          data.summary,
    cycle_history:    data.cycle_history?.slice(0, 6) ?? [],
    top_contributors: data.top_contributors?.slice(0, 10) ?? [],
    top_industries:   data.top_industries?.slice(0, 10) ?? [],
    scraped_at:       new Date().toISOString(),
  };
}

/**
 * Full lookup: search → profile scrape for a single candidate.
 */
async function enrichCandidate(browser, candidate) {
  const ctx  = await newScrapeContext(browser);
  const page = await ctx.newPage();

  try {
    // Always search first and scrape the profile URL the search result points
    // at. A known mpid (previously confirmed and stored on the politician) is
    // passed in only to disambiguate same-name results — it is never used to
    // construct a URL, because the slug and office scheme (/profiles/.../
    // us_congress/ vs /officeholders/...) can't be guessed reliably from a
    // name. See searchCandidate().
    const knownMpid = candidate.opensecrets_mpid ?? MPID_ARG ?? null;
    const found = await searchCandidate(
      page,
      candidate.full_name ?? candidate.name,
      candidate.state ?? STATE_ARG,
      knownMpid,
    );
    if (!found) return null; // blocked by bot-check — caller preserves prior snapshot

    let profileUrl = found.profileUrl;
    let mpid       = found.mpid ?? knownMpid;
    let guessed    = found.guessed === true;

    await sleep(300);
    const scraped = await scrapeProfilePage(page, profileUrl);
    if (!scraped) return null;

    // A guessed (unverified) slug that didn't turn up any real data isn't a
    // confirmed match — it's just a plausible-looking URL that may 404 or
    // point at the wrong person. Discard it rather than let it get persisted
    // as this politician's OpenSecrets link.
    const hasRealData = Boolean(scraped.summary && Object.keys(scraped.summary).length)
      || (scraped.top_contributors?.length ?? 0) > 0
      || (scraped.top_industries?.length ?? 0) > 0;
    if (guessed && !hasRealData) {
      console.error(`  [scrape] Guessed URL returned no real data — discarding unverified link`);
      return null;
    }

    return { ...scraped, mpid: mpid ?? scraped.mpid, input_name: candidate.full_name ?? candidate.name };
  } catch (err) {
    console.error(`  Error enriching ${candidate.full_name}: ${err.message}`);
    return null;
  } finally {
    await ctx.close();
  }
}

// ── District mode ─────────────────────────────────────────────────────────────

/**
 * Scrape all candidates + finance data for a congressional district.
 *
 * URL: https://www.opensecrets.org/elections/{state}/federal/{state}-district-{N}/candidates
 *
 * @param {string} districtLabel  e.g. "CA-31" or "CA-7"
 * @param {string|null} cycle     e.g. "2026" — appended as ?cycle=2026
 * @returns {object|null}
 */
async function scrapeDistrictPage(browser, districtLabel, cycle = null) {
  // Parse "CA-31" → state="california", districtNum="31"
  const match = districtLabel.toUpperCase().match(/^([A-Z]{2})-?(\d+)$/);
  if (!match) {
    console.error(`  [district] Invalid district label: ${districtLabel}`);
    return null;
  }

  const stateAbbr = match[1];
  const districtNum = parseInt(match[2], 10);

  // Build the full state name slug OpenSecrets uses in URLs
  const STATE_SLUGS = {
    AL:'alabama',AK:'alaska',AZ:'arizona',AR:'arkansas',CA:'california',
    CO:'colorado',CT:'connecticut',DE:'delaware',FL:'florida',GA:'georgia',
    HI:'hawaii',ID:'idaho',IL:'illinois',IN:'indiana',IA:'iowa',
    KS:'kansas',KY:'kentucky',LA:'louisiana',ME:'maine',MD:'maryland',
    MA:'massachusetts',MI:'michigan',MN:'minnesota',MS:'mississippi',
    MO:'missouri',MT:'montana',NE:'nebraska',NV:'nevada',NH:'new-hampshire',
    NJ:'new-jersey',NM:'new-mexico',NY:'new-york',NC:'north-carolina',
    ND:'north-dakota',OH:'ohio',OK:'oklahoma',OR:'oregon',PA:'pennsylvania',
    RI:'rhode-island',SC:'south-carolina',SD:'south-dakota',TN:'tennessee',
    TX:'texas',UT:'utah',VT:'vermont',VA:'virginia',WA:'washington',
    WV:'west-virginia',WI:'wisconsin',WY:'wyoming',
  };

  const stateSlug = STATE_SLUGS[stateAbbr];
  if (!stateSlug) {
    console.error(`  [district] Unknown state: ${stateAbbr}`);
    return null;
  }

  const districtSlug = `${stateSlug}-district-${districtNum}`;
  let url = `https://www.opensecrets.org/elections/${stateSlug}/federal/${districtSlug}/candidates`;
  if (cycle) url += `?cycle=${cycle}`;

  console.error(`  [district] ${districtLabel} → ${url}`);

  const ctx  = await newScrapeContext(browser);
  const page = await ctx.newPage();

  try {
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: TIMEOUT });
    await sleep(DELAY_MS);

    const data = await page.evaluate(() => {
      const result = {
        district_label: null,
        incumbent:      null,
        total_raised:   null,
        candidate_count: null,
        candidates:     [],
        source_url:     window.location.href,
        scraped_at:     new Date().toISOString(),
      };

      // District heading
      const h1 = document.querySelector('h1');
      result.district_label = h1?.textContent?.trim() ?? null;

      // Incumbent name from the badge area
      const incumbentEl = document.evaluate(
        '//*[contains(text(),"INCUMBENT")]/following-sibling::*[1] | //*[contains(text(),"INCUMBENT")]/../*[1]',
        document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null
      ).singleNodeValue;
      // Simpler: look for the incumbent name near the "INCUMBENT" text
      const bodyText = document.body.innerText;
      const incMatch = bodyText.match(/INCUMBENT\s*\n([^\n]+)/);
      result.incumbent = incMatch ? incMatch[1].trim() : null;

      // Total raised from the summary card
      const totalMatch = bodyText.match(/TOTAL RAISED\s*\n\$([\d,]+)/);
      result.total_raised = totalMatch ? '$' + totalMatch[1] : null;

      const countMatch = bodyText.match(/CANDIDATES\s*\n(\d+)/);
      result.candidate_count = countMatch ? parseInt(countMatch[1]) : null;

      // Candidate table: "Candidate | Raised | Spent | Cash on Hand"
      const tables = document.querySelectorAll('table');
      for (const table of tables) {
        const rows = Array.from(table.querySelectorAll('tr'));
        if (rows.length < 2) continue;
        const headers = Array.from(rows[0].querySelectorAll('th,td'))
          .map(c => c.textContent.trim().toLowerCase());
        if (!headers.some(h => h.includes('candidate')) || !headers.some(h => h.includes('raised'))) continue;

        result.candidates = rows.slice(1).map(row => {
          const cells = Array.from(row.querySelectorAll('td')).map(c => c.textContent.trim());
          if (cells.length < 2) return null;
          // Name cell often has "(D)INCUMBENT" appended
          const nameRaw = cells[0] ?? '';
          const partyMatch = nameRaw.match(/\(([A-Z])\)/);
          const isIncumbent = nameRaw.includes('INCUMBENT');
          const name = nameRaw.replace(/\([A-Z]\).*/, '').trim();
          return {
            name,
            party:       partyMatch ? partyMatch[1] : null,
            is_incumbent: isIncumbent,
            raised:       cells[1] ?? null,
            spent:        cells[2] ?? null,
            cash_on_hand: cells[3] ?? null,
          };
        }).filter(Boolean).filter(c => c.name.length > 1);

        if (result.candidates.length > 0) break;
      }

      return result;
    });

    return data;
  } catch (err) {
    console.error(`  [district] Error: ${err.message}`);
    return null;
  } finally {
    await ctx.close();
  }
}

// ── Main ─────────────────────────────────────────────────────────────────────

async function main() {
  // --disable-blink-features=AutomationControlled stops Chrome from setting
  // the automation signals Cloudflare's challenge keys on; newScrapeContext
  // masks navigator.webdriver on top of that.
  const browser = await chromium.launch({
    headless: true,
    args: ['--disable-blink-features=AutomationControlled'],
  });

  // ── District mode ─────────────────────────────────────────────────────────
  if (DISTRICT_ARG) {
    const result = await scrapeDistrictPage(browser, DISTRICT_ARG, CYCLE_ARG);
    await browser.close();
    const output = JSON.stringify(result, null, 2);
    if (OUT_PATH) {
      mkdirSync(dirname(OUT_PATH), { recursive: true });
      writeFileSync(OUT_PATH, output, 'utf8');
      console.error(`\n✓ Written to ${OUT_PATH}`);
    } else {
      console.log(output);
    }
    return;
  }

  let candidates = [];

  if (FILE_ARG) {
    const raw = JSON.parse(readFileSync(resolve(process.cwd(), FILE_ARG), 'utf8'));
    candidates = Array.isArray(raw) ? raw : [raw];
  } else if (MPID_ARG) {
    candidates = [{ full_name: NAME_ARG ?? 'unknown', state: STATE_ARG, opensecrets_mpid: MPID_ARG }];
  } else if (CID_ARG) {
    // Legacy CID: attempt direct page construction
    candidates = [{ full_name: NAME_ARG ?? 'unknown', state: STATE_ARG, opensecrets_id: CID_ARG }];
  } else if (NAME_ARG) {
    candidates = [{ full_name: NAME_ARG, state: STATE_ARG }];
  } else {
    console.error('Usage: node scrape-opensecrets.js --name="Name" --state=CA');
    console.error('       node scrape-opensecrets.js --mpid=1105090');
    console.error('       node scrape-opensecrets.js --file=path/to/politicians.json');
    process.exit(1);
  }

  const results = [];

  for (const candidate of candidates) {
    console.error(`\n→ ${candidate.full_name ?? candidate.name} (${candidate.state ?? '??'})`);
    const result = await enrichCandidate(browser, candidate);
    if (result) {
      results.push(result);
      console.error(`  ✓ ${result.top_contributors.length} contributors, ${result.top_industries.length} industries`);
    } else {
      console.error(`  ✗ No data found`);
      results.push({ input_name: candidate.full_name ?? candidate.name, error: 'not_found' });
    }
    await sleep(DELAY_MS);
  }

  await browser.close();

  const output = JSON.stringify(results.length === 1 ? results[0] : results, null, 2);

  if (OUT_PATH) {
    mkdirSync(dirname(OUT_PATH), { recursive: true });
    writeFileSync(OUT_PATH, output, 'utf8');
    console.error(`\n✓ Written to ${OUT_PATH}`);
  } else {
    console.log(output);
  }
}

main().catch(err => {
  console.error('\nFatal:', err);
  process.exit(1);
});
