/**
 * Ballotpedia 2026 Election Candidate Scraper
 *
 * Scrapes primary election results from Ballotpedia for the 2026 U.S. House,
 * Senate, and statewide executive offices, outputting a JSON array compatible with:
 *   php artisan politicians:import-ballotpedia --file=storage/app/imports/ballotpedia-2026.json
 *
 * Usage:
 *   node scripts/scrape-ballotpedia.js [--office=<filter>] [--state=CA] [--out=path/to/output.json]
 *   node scripts/scrape-ballotpedia.js --office=statewide --state=CA --cache-hours=3 --out=...
 *
 * --cache-hours=N skips the scrape entirely (no browser launch) if --out
 * already exists and is younger than N hours. Default 0 (always scrape).
 *
 * --office values:
 *   all              All federal + statewide offices (default)
 *   federal          U.S. House + Senate only
 *   statewide        All statewide executive offices only
 *   house            U.S. House only
 *   senate           U.S. Senate only
 *   governor         Gubernatorial races
 *   lt_governor      Lieutenant Governor races
 *   ag               Attorney General races
 *   treasurer        State Treasurer races
 *   controller       State Controller races
 *   secretary_state  Secretary of State races
 *
 * --strategy values:
 *   index   (default) Visit the central Ballotpedia index page per office,
 *           collect race links, then visit each race page individually.
 *   direct  Construct per-state race URLs directly from the known Ballotpedia
 *           URL pattern (more reliable for statewide offices, all 50 states).
 *   widget  Visit the per-state overview page (Elections_in_{State},_{year})
 *           and parse the bp-table widget tables there. Covers ALL offices
 *           (Senate, House all districts, all statewide) in a single page per
 *           state and uses data-cell attributes for reliable extraction.
 *
 * Requirements:
 *   npm install playwright
 *   npx playwright install chromium
 */

import { chromium } from 'playwright';
import { writeFileSync, mkdirSync, existsSync, statSync, readFileSync } from 'fs';
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

const OFFICE_FILTER  = (args.office ?? 'all').toLowerCase();
const STATE_FILTER   = args.state ? args.state.toUpperCase() : null;
/** When true, also scrape per-race result status (winner / loser / incumbent). */
const WITH_RESULTS   = args.results === 'true';
/** When true, visit each candidate's Ballotpedia profile to extract campaign website + bio. */
const FETCH_WEBSITES = args['fetch-websites'] === 'true';
/** Which election year to target. Defaults to 2026 (next cycle). */
const ELECTION_YEAR  = args.year ? parseInt(args.year, 10) : 2026;
const OUT_PATH       = args.out
  ? resolve(process.cwd(), args.out)
  : resolve(__dirname, `../storage/app/imports/ballotpedia-${ELECTION_YEAR}.json`);

/**
 * --cache-hours=N — if the output JSON already exists and was written within
 * N hours, skip launching Playwright entirely and exit early, reusing the
 * file already on disk. Set 0 (default) to always scrape.
 */
const CACHE_HOURS = args['cache-hours'] ? parseFloat(args['cache-hours']) : 0;

/**
 * --strategy=direct  bypasses the Ballotpedia index pages and constructs
 * per-state race URLs directly from the known Ballotpedia URL pattern.
 * This is more reliable than index scraping and guarantees all 50 states
 * are attempted even if the index page doesn't list some races yet.
 *
 * --strategy=index  (default) uses the original index-page discovery approach.
 *
 * --strategy=widget  visits the per-state overview page
 * (https://ballotpedia.org/Elections_in_{StateName},_{year}) and parses
 * the bp-table widget tables. These tables use data-cell attributes on
 * every <td> (candidate/office/party/status) making them the most
 * structurally reliable source. One page per state covers ALL offices:
 * U.S. Senate, every House district, Governor, Lt. Gov., AG, etc.
 */
const STRATEGY = (args.strategy ?? 'index').toLowerCase();

// Ballotpedia index pages for each office type
const ELECTION_INDEXES = [
  // ── Federal ──────────────────────────────────────────────────────────────
  {
    key: 'house',
    group: 'federal',
    url: `https://ballotpedia.org/United_States_House_of_Representatives_elections,_${ELECTION_YEAR}`,
    office: 'U.S. Representative',
    governance_level: 'Federal',
  },
  {
    key: 'senate',
    group: 'federal',
    url: `https://ballotpedia.org/United_States_Senate_elections,_${ELECTION_YEAR}`,
    office: 'U.S. Senator',
    governance_level: 'Federal',
  },
  // ── Statewide Executive ───────────────────────────────────────────────────
  {
    key: 'governor',
    group: 'statewide',
    url: `https://ballotpedia.org/Gubernatorial_elections,_${ELECTION_YEAR}`,
    office: 'Governor',
    governance_level: 'State',
  },
  {
    key: 'lt_governor',
    group: 'statewide',
    url: `https://ballotpedia.org/Lieutenant_gubernatorial_elections,_${ELECTION_YEAR}`,
    office: 'Lieutenant Governor',
    governance_level: 'State',
  },
  {
    key: 'ag',
    group: 'statewide',
    url: `https://ballotpedia.org/State_attorney_general_elections,_${ELECTION_YEAR}`,
    office: 'Attorney General',
    governance_level: 'State',
  },
  {
    key: 'treasurer',
    group: 'statewide',
    url: `https://ballotpedia.org/State_treasurer_elections,_${ELECTION_YEAR}`,
    office: 'State Treasurer',
    governance_level: 'State',
  },
  {
    key: 'controller',
    group: 'statewide',
    url: `https://ballotpedia.org/State_controller_elections,_${ELECTION_YEAR}`,
    office: 'State Controller',
    governance_level: 'State',
  },
  {
    key: 'secretary_state',
    group: 'statewide',
    url: `https://ballotpedia.org/Secretary_of_State_elections,_${ELECTION_YEAR}`,
    office: 'Secretary of State',
    governance_level: 'State',
  },
];

// Two-letter state abbreviation lookup
const STATE_ABBR = {
  Alabama: 'AL', Alaska: 'AK', Arizona: 'AZ', Arkansas: 'AR',
  California: 'CA', Colorado: 'CO', Connecticut: 'CT', Delaware: 'DE',
  Florida: 'FL', Georgia: 'GA', Hawaii: 'HI', Idaho: 'ID',
  Illinois: 'IL', Indiana: 'IN', Iowa: 'IA', Kansas: 'KS',
  Kentucky: 'KY', Louisiana: 'LA', Maine: 'ME', Maryland: 'MD',
  Massachusetts: 'MA', Michigan: 'MI', Minnesota: 'MN', Mississippi: 'MS',
  Missouri: 'MO', Montana: 'MT', Nebraska: 'NE', Nevada: 'NV',
  'New Hampshire': 'NH', 'New Jersey': 'NJ', 'New Mexico': 'NM',
  'New York': 'NY', 'North Carolina': 'NC', 'North Dakota': 'ND',
  Ohio: 'OH', Oklahoma: 'OK', Oregon: 'OR', Pennsylvania: 'PA',
  'Rhode Island': 'RI', 'South Carolina': 'SC', 'South Dakota': 'SD',
  Tennessee: 'TN', Texas: 'TX', Utah: 'UT', Vermont: 'VT',
  Virginia: 'VA', Washington: 'WA', 'West Virginia': 'WV',
  Wisconsin: 'WI', Wyoming: 'WY', 'District of Columbia': 'DC',
};

/**
 * Direct per-state URL templates for each statewide office.
 * Key = ELECTION_INDEXES key. Value = a function(stateName, year) → URL.
 *
 * Ballotpedia's URL convention for statewide races is consistent:
 *   {StateName}_{office_label}_election,_{YEAR}
 *
 * Where "StateName" uses underscores for spaces and matches the official
 * Ballotpedia article title exactly.
 *
 * These are used when --strategy=direct to construct 50 URLs at once
 * instead of relying on the central index page listing all races.
 */
const DIRECT_URL_TEMPLATES = {
  governor:        (s, y) => `https://ballotpedia.org/${s}_gubernatorial_election,_${y}`,
  lt_governor:     (s, y) => `https://ballotpedia.org/${s}_lieutenant_gubernatorial_election,_${y}`,
  ag:              (s, y) => `https://ballotpedia.org/${s}_attorney_general_election,_${y}`,
  treasurer:       (s, y) => `https://ballotpedia.org/${s}_State_Treasurer_election,_${y}`,
  controller:      (s, y) => `https://ballotpedia.org/${s}_State_Controller_election,_${y}`,
  secretary_state: (s, y) => `https://ballotpedia.org/${s}_Secretary_of_State_election,_${y}`,
  // Verified against the live site 2026-08-02 — NOT "{State}_Senate_election,_{y}"
  // (that pattern 404s). The real Ballotpedia title mirrors the House pattern below.
  senate:          (s, y) => `https://ballotpedia.org/United_States_Senate_election_in_${s},_${y}`,
  // House has no single per-state URL (it's one race per district) — see
  // buildHouseDirectRaces() below, which builds one URL per district instead.
};

/**
 * U.S. House seats per state (119th Congress apportionment). Mirrors
 * DISTRICT_COUNTS in resources/js/map/config/constants.js — kept as a
 * separate local copy since this script has no build step wiring it to the
 * frontend bundle. States with count===1 are at-large (single statewide
 * House seat) and use a different Ballotpedia URL pattern than multi-district
 * states — see buildHouseDirectRaces().
 */
const HOUSE_DISTRICT_COUNTS = {
  Alabama: 7, Alaska: 1, Arizona: 9, Arkansas: 4, California: 52,
  Colorado: 8, Connecticut: 5, Delaware: 1, Florida: 28, Georgia: 14,
  Hawaii: 2, Idaho: 2, Illinois: 17, Indiana: 9, Iowa: 4, Kansas: 4,
  Kentucky: 6, Louisiana: 6, Maine: 2, Maryland: 8, Massachusetts: 9,
  Michigan: 13, Minnesota: 8, Mississippi: 4, Missouri: 8, Montana: 2,
  Nebraska: 3, Nevada: 4, 'New Hampshire': 2, 'New Jersey': 12, 'New Mexico': 3,
  'New York': 26, 'North Carolina': 14, 'North Dakota': 1, Ohio: 15, Oklahoma: 5,
  Oregon: 6, Pennsylvania: 17, 'Rhode Island': 2, 'South Carolina': 7,
  'South Dakota': 1, Tennessee: 9, Texas: 38, Utah: 4, Vermont: 1,
  Virginia: 11, Washington: 10, 'West Virginia': 2, Wisconsin: 8, Wyoming: 1,
};

/** "1" → "1st", "2" → "2nd", "3" → "3rd", "11"/"12"/"13" → "...th". */
function ordinal(n) {
  const j = n % 10, k = n % 100;
  if (j === 1 && k !== 11) return `${n}st`;
  if (j === 2 && k !== 12) return `${n}nd`;
  if (j === 3 && k !== 13) return `${n}rd`;
  return `${n}th`;
}

/**
 * Build one race URL per U.S. House seat — every congressional district for
 * multi-district states, or one statewide race for at-large states. Ballotpedia
 * has no single per-state House overview page (that was the broken assumption
 * behind --strategy=widget's "Elections_in_{State},_{year}" page, which 404s),
 * so House needs its own per-district builder instead of DIRECT_URL_TEMPLATES.
 *
 * Verified against the live site 2026-08-02:
 *   multi-district: "California's 33rd Congressional District election, 2026"
 *   at-large:       "United States House of Representatives election in Alaska, 2026"
 *
 * Uses a literal apostrophe, not %27 — see the "Do NOT encode apostrophes"
 * note in scrapeRaceLinks() below; the same server-redirect issue applies here.
 */
function buildHouseDirectRaces(houseConfig) {
  const races = [];
  for (const [stateName, stateAbbr] of Object.entries(STATE_ABBR)) {
    if (stateName === 'District of Columbia') continue; // non-voting delegate, not a House race
    if (STATE_FILTER && stateAbbr !== STATE_FILTER) continue;
    const totalDistricts = HOUSE_DISTRICT_COUNTS[stateName];
    if (!totalDistricts) continue;
    const slug = stateName.replace(/ /g, '_');

    if (totalDistricts === 1) {
      races.push({
        url: `https://ballotpedia.org/United_States_House_of_Representatives_election_in_${slug},_${ELECTION_YEAR}`,
        stateAbbr, stateName, chamberConfig: houseConfig,
        district: `${stateAbbr}-AL`,
      });
      continue;
    }

    for (let d = 1; d <= totalDistricts; d++) {
      races.push({
        url: `https://ballotpedia.org/${slug}'s_${ordinal(d)}_Congressional_District_election,_${ELECTION_YEAR}`,
        stateAbbr, stateName, chamberConfig: houseConfig,
        district: `${stateAbbr}-${String(d).padStart(2, '0')}`,
      });
    }
  }
  return races;
}

/**
 * Build the list of { url, stateAbbr, key } race descriptors for direct mode.
 * Each statewide office × every state (or filtered state) produces one URL.
 */
function buildDirectRaceList(indexes) {
  const races = [];
  for (const cfg of indexes) {
    const tmpl = DIRECT_URL_TEMPLATES[cfg.key];
    if (!tmpl) {
      console.warn(`  [direct] No URL template for office "${cfg.key}" — falling back to index strategy for this office.`);
      continue;
    }
    for (const [stateName, stateAbbr] of Object.entries(STATE_ABBR)) {
      if (stateName === 'District of Columbia') continue; // DC rarely has statewide races
      if (STATE_FILTER && stateAbbr !== STATE_FILTER) continue;
      const slug = stateName.replace(/ /g, '_');
      races.push({
        url: tmpl(slug, ELECTION_YEAR),
        stateAbbr,
        stateName,
        chamberConfig: cfg,
      });
    }
  }
  return races;
}

/** Delay between page requests to avoid hammering Ballotpedia. */
const DELAY_MS = 1500;

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Extract state abbreviation from a page title or URL segment.
 * e.g. "California's 1st Congressional District election, 2026" → "CA"
 */
function parseStateFromTitle(title) {
  for (const [name, abbr] of Object.entries(STATE_ABBR)) {
    if (title.startsWith(name)) return abbr;
  }
  return null;
}

/**
 * Extract district label from a House race title.
 * e.g. "California's 33rd Congressional District election, 2026" → "CA-33"
 */
function parseDistrictLabel(title, stateAbbr) {
  if (!stateAbbr) return null;
  const m = title.match(/(\d+)(st|nd|rd|th)\s+Congressional District/i);
  if (m) return `${stateAbbr}-${m[1].padStart(2, '0')}`;
  if (/at-large/i.test(title)) return `${stateAbbr}-AL`;
  return null;
}

/**
 * Normalise party name to a consistent label.
 */
function normaliseParty(raw) {
  if (!raw) return null;
  const p = raw.trim().toLowerCase();
  if (p.includes('democrat')) return 'Democratic';
  if (p.includes('republican')) return 'Republican';
  if (p.includes('libertarian')) return 'Libertarian';
  if (p.includes('green')) return 'Green';
  if (p.includes('independent') || p === 'ind') return 'Independent';
  // California "No Party Preference" is a ballot-access designation, not a party.
  // Return null so no misleading badge is stored — same logic as PHP normaliseParty().
  if (p.includes('no party') || p.includes('non-partisan') || p.includes('nonpartisan')
      || p === 'npp' || p === 'dts' /* decline to state */) return null;
  return raw.trim();
}

/**
 * Index strategy: for each office visit the central Ballotpedia index page,
 * collect race links, then scrape each race page.
 * Shared by both the default index strategy and the direct-strategy fallback.
 *
 * @param {Array}    indexes      - Subset of ELECTION_INDEXES to process
 * @param {Function} newPage      - Factory function returning a fresh Playwright page
 * @param {Array}    allCandidates - Output array; results are pushed here in place
 */
async function runIndexStrategy(indexes, newPage, allCandidates) {
  for (const chamberConfig of indexes) {
    console.log(`\n[${ chamberConfig.key.toUpperCase() }] Scraping index page…`);

    const indexPage = await newPage();
    let raceLinks;
    try {
      raceLinks = await scrapeRaceLinks(indexPage, chamberConfig.url, chamberConfig.key);
    } catch (err) {
      console.error(`  ✗ Failed to scrape index: ${err.message}`);
      await indexPage.context().close();
      continue;
    }
    await indexPage.context().close();

    console.log(`  Found ${raceLinks.length} race pages.`);

    for (const { url: raceUrl, text: raceText } of raceLinks) {
      const slugText = decodeURIComponent(raceUrl.split('/').pop() ?? '').replace(/_/g, ' ');
      const stateAbbr = parseStateFromTitle(raceText || slugText);

      if (STATE_FILTER && stateAbbr !== STATE_FILTER) continue;

      let raceData;
      try {
        raceData = await scrapeRacePageWithRetry(newPage, raceUrl, chamberConfig.key, WITH_RESULTS);
      } catch (err) {
        console.warn(`  ✗ Skipped ${raceUrl}: ${err.message}`);
        continue;
      }

      const { pageTitle, candidates } = raceData;
      const district = chamberConfig.key === 'house'
        ? parseDistrictLabel(pageTitle, stateAbbr)
        : null;

      for (const c of candidates) {
        const fullName = c.name;
        if (!fullName || fullName.length < 3) continue;

        // Optionally fetch campaign website from the candidate's Ballotpedia profile
        let campaignWebsite = null;
        let bioExcerpt = null;
        if (FETCH_WEBSITES && c.ballotpedia_url) {
          const profile = await scrapeCandidateProfile(newPage, c.ballotpedia_url);
          if (profile) {
            campaignWebsite = profile.campaignWebsite ?? null;
            bioExcerpt      = profile.bioExcerpt ?? null;
          }
          await sleep(300);
        }

        allCandidates.push({
          full_name: fullName,
          political_office: chamberConfig.office,
          governance_level: chamberConfig.governance_level,
          state: stateAbbr ?? null,
          district: district ?? null,
          party_affiliation: normaliseParty(c.party),
          election_date: `${ELECTION_YEAR}-11-03`,
          is_running_candidate: c.result_status == null,
          result_status: c.result_status ?? null,
          ballotpedia_url: c.ballotpedia_url ?? raceUrl,
          campaign_website: campaignWebsite,
          bio_excerpt: bioExcerpt,
          source_page: raceUrl,
          scraped_at: new Date().toISOString(),
        });
      }

      if (candidates.length > 0) {
        console.log(`  ✓ ${stateAbbr ?? '??'} ${district ?? chamberConfig.key} — ${candidates.length} candidate(s)`);
      }
    }
  }
}

/**
 * Scrape all race URLs from a chamber index page.
 * Returns an array of { url, stateAbbr, district } objects.
 */
async function scrapeRaceLinks(page, indexUrl, chamber) {
  console.log(`  → Fetching index: ${indexUrl}`);
  await page.goto(indexUrl, { waitUntil: 'domcontentloaded', timeout: 30_000 });
  await sleep(DELAY_MS);

  const links = await page.evaluate((year) => {
    const results = [];
    const anchors = document.querySelectorAll('#mw-content-text a[href]');
    for (const a of anchors) {
      const href = a.getAttribute('href');
      if (!href) continue;
      // Match race pages: "/California's_1st_Congressional_District_election,_2026"
      if (href.includes(`election,_${year}`) && !href.includes('#')) {
        const text = (a.textContent ?? '').trim();
        // Strip any trailing punctuation (colon, period, comma) that Ballotpedia
        // sometimes appends to href values. Do NOT encode apostrophes as %27 —
        // Ballotpedia's server 301-redirects %27 back to the canonical literal '
        // which destroys the page.evaluate() execution context mid-flight.
        const cleanHref = href.replace(/[:.,']+$/, '');
        const full = cleanHref.startsWith('http') ? cleanHref : 'https://ballotpedia.org' + cleanHref;
        results.push({ url: full, text });
      }
    }
    // Deduplicate by URL
    const seen = new Set();
    return results.filter(r => seen.has(r.url) ? false : (seen.add(r.url), true));
  }, ELECTION_YEAR);

  return links;
}

/**
 * Return true if an href looks like a genuine Ballotpedia candidate profile page.
 * Rejects: external URLs, election index pages, query strings, fragment anchors,
 * mailto/javascript, and anything that would overflow VARCHAR(255).
 * Pure function — called from both Node context and page.evaluate() context.
 */
function isValidBpHref(h) {
  if (!h || h.length < 3 || h.length > 255) return false;
  const isInternal =
    (h.startsWith('/') && !h.startsWith('//'))
    || h.startsWith('https://ballotpedia.org/');
  if (!isInternal) return false;
  if (h.includes('?') || h.includes('#') || h.includes('%3F') || h.includes('%23')) return false;
  if (h.includes(':')) return false; // mailto:, javascript:, etc.
  if (/elections?,_\d{4}/i.test(h)) return false; // election index pages
  return true;
}

/**
 * Resolve a validated href to a full https://ballotpedia.org/ URL.
 */
function resolveBpHref(h) {
  return h.startsWith('https://') ? h : 'https://ballotpedia.org' + h;
}

/**
 * Scrape candidates from a single race page (e.g. California gubernatorial election, 2026).
 *
 * THREE-STRATEGY APPROACH — tried in order, results merged and de-duped:
 *
 *  Strategy A — wikitable scan (most reliable for statewide races)
 *    Finds ALL <table class="wikitable"> on the page regardless of heading
 *    context. Ballotpedia wraps tables in <div> containers so they are NEVER
 *    direct siblings of the section heading — the old sibling-walk missed them.
 *    Detects candidate tables by checking header row text for "candidate" /
 *    "party" / "name". Handles variable column layouts by finding the name
 *    anchor link dynamically rather than assuming a fixed cell index.
 *
 *  Strategy B — heading-scoped deep search
 *    Walks every element *inside* sections whose heading text mentions
 *    "candidate" or "general election" (not just direct siblings). Descends
 *    into <div> wrappers to find tables and lists.
 *
 *  Strategy C — <ul> candidate lists (infobox / sidebar format)
 *    Parses <li> items in the form "Name (Party)" anywhere in the article body.
 *
 * Returns an array of candidate objects.
 */
async function scrapeRacePage(page, raceUrl, chamber, withResults = false) {
  // Use 'domcontentloaded' — Ballotpedia pages emit endless background analytics
  // / AdSense / lazy-image requests, so 'networkidle' never settles and the
  // navigation hits the full timeout. The DOM (tables, headings, anchors) we
  // evaluate below is fully present after DOMContentLoaded.
  const response = await page.goto(raceUrl, { waitUntil: 'domcontentloaded', timeout: 30_000 });

  // Ballotpedia's CloudFront/AWS WAF sometimes answers with HTTP 202 and an
  // empty placeholder body (a JS bot-challenge) instead of the real article —
  // confirmed manually via curl (x-amzn-waf-action: challenge header) on
  // 2026-08-02. Without this check that page evaluates to 0 candidates with
  // NO thrown error, so it silently disappears (no ✗ log line) instead of
  // being retried. Throw so scrapeRacePageWithRetry's retry loop catches it.
  if (response && response.status() === 202) {
    throw new Error(`WAF challenge response (HTTP 202) for ${raceUrl}`);
  }

  await sleep(DELAY_MS);

  return page.evaluate((args) => {
    const { raceUrl, ELECTION_YEAR, withResults } = args;

    const titleEl = document.querySelector('h1#firstHeading') ?? document.querySelector('h1.firstHeading');
    const pageTitle = (titleEl?.textContent ?? '').trim();

    // ── Helpers (re-defined inside evaluate so they run in the page context) ──

    function isBpHref(h) {
      if (!h || h.length < 3 || h.length > 255) return false;
      const isInternal = (h.startsWith('/') && !h.startsWith('//'))
        || h.startsWith('https://ballotpedia.org/');
      if (!isInternal) return false;
      if (h.includes('?') || h.includes('#') || h.includes('%3F') || h.includes('%23')) return false;
      if (h.includes(':')) return false;
      if (/elections?,_\d{4}/i.test(h)) return false;
      return true;
    }

    function resolveBp(h) {
      return h.startsWith('https://') ? h : 'https://ballotpedia.org' + h;
    }

    /** Find the first valid Ballotpedia profile anchor within an element. */
    function findBpAnchor(el) {
      for (const a of el.querySelectorAll('a[href]')) {
        const h = a.getAttribute('href') ?? '';
        if (isBpHref(h)) return resolveBp(h);
      }
      return null;
    }

    /** Extract result status from a table row's full text + cells. */
    function extractResult(row, cells) {
      if (!withResults) return null;
      const rowText = row.textContent ?? '';
      // Checkmarks and explicit win/loss text
      if (/✓|✔|✅/.test(rowText) || /\bwon\b|\belected\b|\badvanced\b|\bwinner\b/i.test(rowText)) {
        // Don't false-positive on header rows
        if (!/^(candidate|name|party|status)/i.test(rowText.trim())) return 'won';
      }
      if (/\blost\b|\bdefeated\b|\beliminated\b/i.test(rowText)) return 'lost';
      // Last cell sometimes contains the plain text "Won" / "Lost"
      const lastText = (cells[cells.length - 1]?.textContent ?? '').trim().toLowerCase();
      if (lastText === 'won' || lastText === 'elected' || lastText === 'advanced') return 'won';
      if (lastText === 'lost' || lastText === 'defeated') return 'lost';
      return null;
    }

    const seen = new Set();
    const results = [];

    function addCandidate(name, party, bpUrl, resultStatus) {
      name = (name ?? '').replace(/\s+/g, ' ').trim();
      if (name.length < 3) return;
      // Skip obvious header/label text
      if (/^(candidate|name|party|status|office|incumbent|running|general|primary)/i.test(name)) return;
      const key = name.toLowerCase();
      if (seen.has(key)) return;
      seen.add(key);
      results.push({ name, party: party || null, ballotpedia_url: bpUrl || null, result_status: resultStatus || null });
    }

    // ── Strategy A: scan ALL wikitables regardless of heading context ─────────
    // Ballotpedia wraps tables in <div>s so they are never direct siblings of
    // section headings. We scan the full document for .wikitable elements and
    // filter to those that look like candidate tables.
    const wikitables = document.querySelectorAll('table.wikitable, table.bptable, table[class*="candidate"], table[class*="election-result"]');

    for (const table of wikitables) {
      const rows = Array.from(table.querySelectorAll('tr'));
      if (rows.length < 2) continue;

      // Inspect the header row to decide if this is a candidate table.
      // Header cells are <th> or the first <tr> of cells.
      const headerRow = rows.find(r => r.querySelector('th')) ?? rows[0];
      const headerText = (headerRow?.textContent ?? '').toLowerCase();

      // Must mention candidate/name/party to be treated as a candidate table.
      // This excludes reference/footnote tables, navboxes, etc.
      const isCandidateTable =
        headerText.includes('candidate') || headerText.includes('name') ||
        headerText.includes('party') || headerText.includes('office');
      if (!isCandidateTable) continue;

      // Build a column index map from header text
      const thCells = Array.from(headerRow.querySelectorAll('th, td'));
      let nameCol = -1, partyCol = -1, resultCol = -1;
      thCells.forEach((th, i) => {
        const t = (th.textContent ?? '').toLowerCase().trim();
        if (nameCol === -1 && (t.includes('candidate') || t.includes('name') || t === '')) nameCol = i;
        if (partyCol === -1 && t.includes('party')) partyCol = i;
        if (resultCol === -1 && (t.includes('result') || t.includes('status') || t.includes('outcome'))) resultCol = i;
      });
      // Default: name in col 1 (after possible color-swatch col 0), party in col 2
      if (nameCol === -1) nameCol = thCells.length > 2 ? 1 : 0;
      if (partyCol === -1) partyCol = nameCol + 1;

      for (const row of rows) {
        if (row === headerRow) continue;
        const cells = Array.from(row.querySelectorAll('td'));
        if (cells.length < 2) continue;

        // Find name: prefer the cell that contains a valid Ballotpedia anchor,
        // then fall back to the header-inferred nameCol.
        let bpUrl = null;
        let nameCellEl = null;
        for (const cell of cells) {
          const href = findBpAnchor(cell);
          if (href) { bpUrl = href; nameCellEl = cell; break; }
        }
        if (!nameCellEl) nameCellEl = cells[nameCol] ?? cells[0];

        const name = (nameCellEl?.textContent ?? '').trim();
        const party = (cells[partyCol]?.textContent ?? '').trim() || null;
        const resultStatus = extractResult(row, cells);

        addCandidate(name, party, bpUrl, resultStatus);
      }
    }

    // ── Strategy B: heading-scoped deep search ──────────────────────────────
    // Walk every element inside candidate-section headings, descending into
    // <div> wrappers that the old sibling-walk missed.
    const headings = document.querySelectorAll('#mw-content-text h2, #mw-content-text h3');
    for (const heading of headings) {
      const ht = (heading.textContent ?? '').toLowerCase();
      if (!ht.includes('candidate') && !ht.includes('general election') && !ht.includes('primary')) continue;

      // Collect all elements until the next same-level (or higher) heading
      const level = heading.tagName; // H2 or H3
      let el = heading.nextElementSibling;
      while (el) {
        if (el.tagName === level || el.tagName === 'H2') break;

        // Tables nested anywhere inside this element
        for (const table of el.querySelectorAll('table')) {
          const rows = Array.from(table.querySelectorAll('tr'));
          for (const row of rows) {
            const cells = Array.from(row.querySelectorAll('td'));
            if (cells.length < 2) continue;
            const bpUrl = (() => { for (const c of cells) { const u = findBpAnchor(c); if (u) return u; } return null; })();
            if (!bpUrl && cells.every(c => !c.textContent?.trim())) continue;
            // Name cell: prefer the one with the bp anchor
            const nameCellEl = bpUrl
              ? cells.find(c => findBpAnchor(c))
              : (cells[1] ?? cells[0]);
            const name = (nameCellEl?.textContent ?? '').trim();
            const party = (cells.find((c, i) => i !== cells.indexOf(nameCellEl) && /democr|republican|libertarian|green|independ|party/i.test(c.textContent ?? ''))?.textContent ?? '').trim() || null;
            addCandidate(name, party, bpUrl, extractResult(row, cells));
          }
        }

        el = el.nextElementSibling;
      }
    }

    // ── Strategy C: <ul> candidate lists (e.g. "Name (Party)" per line) ─────
    const contentEl = document.querySelector('#mw-content-text');
    if (contentEl) {
      for (const li of contentEl.querySelectorAll('li')) {
        const text = (li.textContent ?? '').trim();
        if (text.length < 3 || text.length > 120) continue;
        const partyMatch = text.match(/\(([^)]{2,40})\)\s*$/);
        if (!partyMatch) continue; // require "(Party)" to avoid false positives
        const name = text.slice(0, text.lastIndexOf('(')).trim();
        const party = partyMatch[1];
        const bpUrl = findBpAnchor(li);
        addCandidate(name, party, bpUrl, null);
      }
    }

    return { pageTitle, candidates: results };
  }, { raceUrl, ELECTION_YEAR, withResults });
}

/**
 * scrapeRacePage() wrapped with retry-on-navigation-destroyed.
 *
 * "page.evaluate: Execution context was destroyed, most likely because of a
 * navigation" happens when Ballotpedia's CloudFront/WAF serves a JS bot-check
 * page instead of the real article — its script redirects to the real page a
 * moment later, which invalidates whatever context Playwright had already
 * grabbed a handle to. Confirmed manually (2026-08-02): re-requesting the
 * exact same URL a few seconds later succeeds cleanly, so this is a transient
 * challenge, not a broken URL — retrying (with a fresh page/context, since a
 * destroyed context can't be reused) recovers the large majority of these.
 *
 * @param {Function} newPage - factory returning a fresh Playwright page
 */
async function scrapeRacePageWithRetry(newPage, raceUrl, chamber, withResults, maxAttempts = 3) {
  let lastErr;
  for (let attempt = 1; attempt <= maxAttempts; attempt++) {
    const page = await newPage();
    try {
      return await scrapeRacePage(page, raceUrl, chamber, withResults);
    } catch (err) {
      lastErr = err;
      const retriable = /Execution context was destroyed|Target closed|Target page.*closed|net::ERR_|WAF challenge/i.test(err.message);
      if (!retriable || attempt === maxAttempts) throw err;
      await sleep(4000 * attempt); // 4s, 8s, ... back off further each attempt
    } finally {
      await page.context().close();
    }
  }
  throw lastErr;
}

/**
 * Visit a candidate's individual Ballotpedia profile page and extract:
 *  - campaign_website: the candidate's official campaign site
 *  - bio_excerpt: first 300 chars of their profile description
 *
 * Only called when --fetch-websites is set. Gracefully returns null on failure.
 */
async function scrapeCandidateProfile(newPage, profileUrl) {
  if (!profileUrl || !profileUrl.startsWith('https://ballotpedia.org/')) return null;

  // Skip Ballotpedia index/election pages — they're not candidate profiles
  if (/elections?,_\d{4}/i.test(profileUrl)) return null;

  const page = await newPage();
  try {
    await page.goto(profileUrl, { waitUntil: 'domcontentloaded', timeout: 20_000 });
    await sleep(300);

    const result = await page.evaluate(() => {
      let campaignWebsite = null;
      let bioExcerpt = null;

      // ── Campaign website from infobox ────────────────────────────────────
      // Ballotpedia infoboxes have rows like:
      //   <th>Campaign website</th><td><a href="https://...">website</a></td>
      const infoboxRows = document.querySelectorAll(
        '.widget-row, table.infobox tr, .votebox tr, .ballotpedia-widget tr'
      );
      for (const row of infoboxRows) {
        const th = row.querySelector('th, td:first-child');
        const td = row.querySelector('td:last-child, td + td');
        if (!th || !td) continue;
        const label = (th.textContent ?? '').toLowerCase().trim();
        if (label.includes('campaign') && label.includes('web')) {
          const a = td.querySelector('a[href]');
          if (a) {
            const href = a.getAttribute('href') ?? '';
            // Only external URLs (not internal Ballotpedia links)
            if (href.startsWith('http') && !href.includes('ballotpedia.org')) {
              campaignWebsite = href;
              break;
            }
          }
        }
      }

      // Fallback: look for any external link labeled "Campaign website" anywhere on page
      if (!campaignWebsite) {
        const allLinks = document.querySelectorAll('a[href]');
        for (const a of allLinks) {
          const text  = (a.textContent ?? '').toLowerCase().trim();
          const href  = a.getAttribute('href') ?? '';
          const title = (a.getAttribute('title') ?? '').toLowerCase();
          if (
            (text === 'campaign website' || text === 'official website' || title.includes('campaign website')) &&
            href.startsWith('http') &&
            !href.includes('ballotpedia.org')
          ) {
            campaignWebsite = href;
            break;
          }
        }
      }

      // ── Bio excerpt from first paragraph of profile ──────────────────────
      const contentEl = document.querySelector('#mw-content-text p, .mw-parser-output p');
      if (contentEl) {
        const text = (contentEl.textContent ?? '').trim().replace(/\s+/g, ' ');
        if (text.length > 30) {
          bioExcerpt = text.substring(0, 300);
        }
      }

      return { campaignWebsite, bioExcerpt };
    });

    return result.campaignWebsite || result.bioExcerpt ? result : null;
  } catch {
    return null;
  } finally {
    await page.context().close();
  }
}

// ── Widget strategy helpers ──────────────────────────────────────────────────

/**
 * Build the per-state Ballotpedia overview page URL for the widget strategy.
 * e.g. "Arkansas" → https://ballotpedia.org/Elections_in_Arkansas,_2026
 */
function buildWidgetStateUrl(stateName, year) {
  return `https://ballotpedia.org/Elections_in_${stateName.replace(/ /g, '_')},_${year}`;
}

/**
 * Map a widget status string to result_status + is_running_candidate.
 * Status text is the combined main text + sub-detail, e.g.:
 *   "On the Ballot General"  → still running
 *   "On the Ballot Primary"  → still running
 *   "Lost Primary"           → eliminated
 *   "Lost General"           → lost
 *   "Won General"            → won
 *   "Advanced General"       → won (advanced to general)
 */
function parseWidgetStatus(statusText) {
  const t = (statusText ?? '').toLowerCase().trim();
  if (/^lost|defeated|eliminated/.test(t)) {
    return { result_status: 'lost', is_running_candidate: false };
  }
  if (/won|elected|advanced/.test(t)) {
    return { result_status: 'won', is_running_candidate: false };
  }
  return { result_status: null, is_running_candidate: true };
}

/**
 * Normalise a Ballotpedia widget office display string to our canonical
 * office name, governance level, and optional district label.
 *
 * Examples:
 *   "U.S. Senate Arkansas"           → { office: 'U.S. Senator', governance_level: 'Federal', district: null }
 *   "U.S. House Arkansas District 1" → { office: 'U.S. Representative', governance_level: 'Federal', district: 'AR-01' }
 *   "Governor"                        → { office: 'Governor', governance_level: 'State', district: null }
 */
function normaliseWidgetOffice(rawOffice, stateAbbr) {
  const s = (rawOffice ?? '').trim();
  if (/^U\.S\.\s+Senate/i.test(s)) {
    return { office: 'U.S. Senator', governance_level: 'Federal', district: null };
  }
  const houseM = s.match(/^U\.S\.\s+House.*?District\s+(\d+)/i);
  if (houseM) {
    return { office: 'U.S. Representative', governance_level: 'Federal', district: `${stateAbbr}-${houseM[1].padStart(2, '0')}` };
  }
  if (/^U\.S\.\s+House/i.test(s)) {
    return { office: 'U.S. Representative', governance_level: 'Federal', district: `${stateAbbr}-AL` };
  }
  if (/^Governor/i.test(s)) {
    return { office: 'Governor', governance_level: 'State', district: null };
  }
  if (/Lieutenant\s+Governor/i.test(s)) {
    return { office: 'Lieutenant Governor', governance_level: 'State', district: null };
  }
  if (/Attorney\s+General/i.test(s)) {
    return { office: 'Attorney General', governance_level: 'State', district: null };
  }
  if (/Treasurer/i.test(s)) {
    return { office: 'State Treasurer', governance_level: 'State', district: null };
  }
  if (/Controller/i.test(s)) {
    return { office: 'State Controller', governance_level: 'State', district: null };
  }
  if (/Secretary\s+of\s+State/i.test(s)) {
    return { office: 'Secretary of State', governance_level: 'State', district: null };
  }
  return { office: s, governance_level: 'State', district: null };
}

/**
 * Scrape all bp-table widget tables from a Ballotpedia state overview page.
 * Returns raw row objects with name, officeName, party, statusText, ballotpediaUrl.
 */
async function scrapeWidgetPage(page, stateUrl, stateAbbr) {
  await page.goto(stateUrl, { waitUntil: 'domcontentloaded', timeout: 30_000 });
  await sleep(DELAY_MS);

  return page.evaluate(({ stateAbbr, stateUrl }) => {
    const rows = [];
    const tables = document.querySelectorAll(
      'table.bp-table.widget-table, table.widget-table'
    );
    if (tables.length === 0) return rows;

    for (const table of tables) {
      for (const tr of table.querySelectorAll('tbody tr')) {
        const candidateCell = tr.querySelector('td[data-cell="candidate"]');
        const officeCell    = tr.querySelector('td[data-cell="office"]');
        const partyCell     = tr.querySelector('td[data-cell="party"]');
        const statusCell    = tr.querySelector('td[data-cell="status"]');

        if (!candidateCell || !officeCell) continue;

        // Name: use only the <a> text inside .widget-candidate-info to avoid
        // picking up the Incumbent/Answered-survey sub-detail spans.
        const nameLink = candidateCell.querySelector('.widget-candidate-info a[href]');
        const name = (nameLink?.textContent ?? candidateCell.textContent ?? '')
          .replace(/\s+/g, ' ').trim();
        if (name.length < 2) continue;

        // Ballotpedia profile URL (strict whitelist — internal only)
        const rawHref = nameLink?.getAttribute('href') ?? '';
        const isValidBpLink = rawHref.length > 2
          && (rawHref.startsWith('/') || rawHref.startsWith('https://ballotpedia.org/'))
          && !rawHref.includes('?') && !rawHref.includes('#');
        const ballotpediaUrl = isValidBpLink
          ? (rawHref.startsWith('https://') ? rawHref : 'https://ballotpedia.org' + rawHref)
          : null;

        // Office name from the link text (e.g. "U.S. Senate Arkansas")
        const officeLink = officeCell.querySelector('a');
        const officeName = (officeLink?.textContent ?? officeCell.textContent ?? '').trim();

        // Party: text content of .party-affiliation span;
        // fallback to class name (dot-Republican → Republican)
        const partySpan = partyCell?.querySelector('[class*="party-affiliation"]');
        let party = (partySpan?.textContent ?? partyCell?.textContent ?? '').trim();
        if (!party && partySpan) {
          const cls = Array.from(partySpan.classList).find(c => c.startsWith('dot-'));
          if (cls) party = cls.replace('dot-', '');
        }

        // Status: full text of the cell (main text + sub-detail joined)
        const statusText = (statusCell?.textContent ?? '').replace(/\s+/g, ' ').trim();

        rows.push({ name, officeName, party, statusText, ballotpediaUrl, sourceUrl: stateUrl });
      }
    }
    return rows;
  }, { stateAbbr, stateUrl });
}

/**
 * Widget strategy: one Ballotpedia state overview page per state covers
 * ALL offices (Senate, every House district, Governor, Lt. Gov., AG, etc.).
 * Respects the --office and --state filters.
 */
async function runWidgetStrategy(newPage, allCandidates) {
  const states = STATE_FILTER
    ? Object.entries(STATE_ABBR).filter(([, abbr]) => abbr === STATE_FILTER)
    : Object.entries(STATE_ABBR);

  console.log(`  [widget] ${states.length} state overview page(s) to visit.\n`);

  for (const [stateName, stateAbbr] of states) {
    const stateUrl = buildWidgetStateUrl(stateName, ELECTION_YEAR);
    console.log(`[WIDGET] ${stateAbbr} — ${stateUrl}`);

    const pg = await newPage();
    let rows;
    try {
      rows = await scrapeWidgetPage(pg, stateUrl, stateAbbr);
    } catch (err) {
      console.warn(`  ✗ ${stateAbbr}: ${err.message}`);
      await pg.context().close();
      continue;
    }
    await pg.context().close();

    if (rows.length === 0) {
      console.log(`  (no widget tables found — page may not exist yet)`);
      continue;
    }

    let added = 0;
    for (const row of rows) {
      const { office, governance_level, district } = normaliseWidgetOffice(row.officeName, stateAbbr);

      // Apply --office filter
      const include =
        OFFICE_FILTER === 'all' ||
        (OFFICE_FILTER === 'federal'         && governance_level === 'Federal') ||
        (OFFICE_FILTER === 'statewide'       && governance_level === 'State') ||
        (OFFICE_FILTER === 'senate'          && office === 'U.S. Senator') ||
        (OFFICE_FILTER === 'house'           && office === 'U.S. Representative') ||
        (OFFICE_FILTER === 'governor'        && office === 'Governor') ||
        (OFFICE_FILTER === 'lt_governor'     && office === 'Lieutenant Governor') ||
        (OFFICE_FILTER === 'ag'              && office === 'Attorney General') ||
        (OFFICE_FILTER === 'treasurer'       && office === 'State Treasurer') ||
        (OFFICE_FILTER === 'controller'      && office === 'State Controller') ||
        (OFFICE_FILTER === 'secretary_state' && office === 'Secretary of State');
      if (!include) continue;

      const { result_status, is_running_candidate } = parseWidgetStatus(row.statusText);

      let campaignWebsite = null;
      let bioExcerpt = null;
      if (FETCH_WEBSITES && row.ballotpediaUrl) {
        const profile = await scrapeCandidateProfile(newPage, row.ballotpediaUrl);
        if (profile) {
          campaignWebsite = profile.campaignWebsite ?? null;
          bioExcerpt      = profile.bioExcerpt ?? null;
        }
        await sleep(300);
      }

      allCandidates.push({
        full_name: row.name,
        political_office: office,
        governance_level,
        state: stateAbbr,
        district: district ?? null,
        party_affiliation: normaliseParty(row.party),
        election_date: `${ELECTION_YEAR}-11-03`,
        is_running_candidate,
        result_status,
        ballotpedia_url: row.ballotpediaUrl ?? row.sourceUrl,
        campaign_website: campaignWebsite,
        bio_excerpt: bioExcerpt,
        source_page: row.sourceUrl,
        scraped_at: new Date().toISOString(),
      });
      added++;
    }

    console.log(`  ✓ ${stateAbbr} — ${added} candidate(s) from ${rows.length} widget rows`);
    await sleep(DELAY_MS);
  }
}

async function main() {
  // ── Cache check: skip Playwright entirely if output JSON is still fresh ──
  // When --cache-hours=N is set and the output file exists and is younger
  // than N hours, exit immediately instead of launching a browser. This
  // stops the hot-state map dispatcher (or a re-triggered manual run) from
  // paying the Playwright cost again when nothing has likely changed on
  // Ballotpedia since the last scrape for this exact office/state/year.
  if (CACHE_HOURS > 0 && existsSync(OUT_PATH)) {
    const ageMs  = Date.now() - statSync(OUT_PATH).mtimeMs;
    const ageHrs = ageMs / 1000 / 3600;
    if (ageHrs < CACHE_HOURS) {
      const existing = JSON.parse(readFileSync(OUT_PATH, 'utf8'));
      console.log(`\n✓ Cache hit — output file is ${ageHrs.toFixed(1)}h old (< ${CACHE_HOURS}h).`);
      console.log(`  ${existing.length} candidate(s) already on disk at ${OUT_PATH}`);
      console.log('  Pass --cache-hours=0 to force a full re-scrape.');
      return;
    }
  }

  const indexes = ELECTION_INDEXES.filter(e => {
    if (OFFICE_FILTER === 'all') return true;
    if (OFFICE_FILTER === 'federal') return e.group === 'federal';
    if (OFFICE_FILTER === 'statewide') return e.group === 'statewide';
    return e.key === OFFICE_FILTER;
  });

  if (indexes.length === 0) {
    console.error(
      `Unknown --office value: ${OFFICE_FILTER}. ` +
      `Use: all, federal, statewide, house, senate, governor, lt_governor, ag, treasurer, controller, secretary_state`
    );
    process.exit(1);
  }

  console.log(`\nBallotpedia ${ELECTION_YEAR} scraper`);
  console.log(`Strategy : ${STRATEGY}`);
  console.log(`Offices  : ${indexes.map(e => e.key).join(', ')} ${STRATEGY === 'widget' ? '(widget covers all — filter applied per-row)' : ''}`);
  console.log(`State    : ${STATE_FILTER ?? 'all 50 states'}`);
  console.log(`Results  : ${WITH_RESULTS ? 'yes (winner/loser capture)' : 'no'}`);
  console.log(`Output   : ${OUT_PATH}\n`);

  const browser = await chromium.launch({ headless: true });

  /**
   * Open a fresh browser context + page.
   * Using a new context per scrape session prevents one page's rogue
   * redirect or destroyed execution context from polluting subsequent pages.
   */
  async function newPage() {
    const ctx = await browser.newContext({
      userAgent: 'Mozilla/5.0 (compatible; U9itus-civic-bot/1.0; +https://u9itus.dev/about)',
      locale: 'en-US',
    });
    return ctx.newPage();
  }

  const allCandidates = [];

  // ─────────────────────────────────────────────────────────────────────────
  // DIRECT strategy: construct per-state (or per-district, for House) URLs
  // from the known BP pattern. Falls back to index only for an office with
  // neither a DIRECT_URL_TEMPLATES entry nor House's dedicated builder.
  // ─────────────────────────────────────────────────────────────────────────
  if (STRATEGY === 'direct') {
    const houseIndex     = indexes.find(e => e.key === 'house');
    const otherIndexes    = indexes.filter(e => e.key !== 'house');
    const fallbackIndexes = otherIndexes.filter(e => !DIRECT_URL_TEMPLATES[e.key]);
    const templateIndexes = otherIndexes.filter(e => DIRECT_URL_TEMPLATES[e.key]);

    if (fallbackIndexes.length > 0) {
      console.log(`  [direct] Offices without a direct template (will use index): ${fallbackIndexes.map(e => e.key).join(', ')}`);
    }

    const directRaces = buildDirectRaceList(templateIndexes);
    const houseRaces  = houseIndex ? buildHouseDirectRaces(houseIndex) : [];
    const allRaces     = [...directRaces, ...houseRaces];
    console.log(`  [direct] ${allRaces.length} race URLs to visit across all states.\n`);

    // Circuit breaker: a handful of consecutive WAF-challenge failures (even
    // after scrapeRacePageWithRetry's own short retries) means we've tripped
    // Ballotpedia's rate-based bot detection for real, not hit a one-off
    // blip — no amount of 2-6s per-request backoff clears that. Pause the
    // whole run for a longer cool-down instead of burning through the rest
    // of the list at a ~0% success rate. Confirmed manually (2026-08-02):
    // hammering this IP with dozens of requests over an hour got EVERY
    // subsequent request 202-challenged even with retries — this is the
    // mitigation for that failure mode.
    const COOLDOWN_THRESHOLD = 4;
    const COOLDOWN_MS = 90_000;
    let consecutiveFailures = 0;

    for (const { url: raceUrl, stateAbbr, stateName, chamberConfig, district } of allRaces) {
      let raceData;
      try {
        raceData = await scrapeRacePageWithRetry(newPage, raceUrl, chamberConfig.key, WITH_RESULTS);
        consecutiveFailures = 0;
      } catch (err) {
        // A 404 means this state simply doesn't have this office (e.g. TX has no Lt. Gov.
        // in the same pattern) — silently skip rather than warn. (Navigation-destroyed /
        // WAF-challenge errors already retried inside scrapeRacePageWithRetry before
        // landing here — reaching this catch means those retries were exhausted too.)
        const isRetriableType = /Execution context was destroyed|WAF challenge|Target closed|net::ERR_/i.test(err.message);
        if (!err.message.includes('404')) {
          console.warn(`  ✗ ${stateAbbr} ${district ?? chamberConfig.key}: ${err.message}`);
        }
        if (isRetriableType) {
          consecutiveFailures++;
          if (consecutiveFailures >= COOLDOWN_THRESHOLD) {
            console.log(`  ⏸ ${consecutiveFailures} consecutive failures — likely rate-limited. Cooling down ${COOLDOWN_MS / 1000}s…`);
            await sleep(COOLDOWN_MS);
            consecutiveFailures = 0;
          }
        }
        continue;
      }

      const { candidates } = raceData;
      if (candidates.length === 0) continue;

      for (const c of candidates) {
        if (!c.name || c.name.length < 3) continue;

        // Optionally fetch campaign website from the candidate's Ballotpedia profile
        let campaignWebsite = null;
        let bioExcerpt = null;
        if (FETCH_WEBSITES && c.ballotpedia_url) {
          const profile = await scrapeCandidateProfile(newPage, c.ballotpedia_url);
          if (profile) {
            campaignWebsite = profile.campaignWebsite ?? null;
            bioExcerpt      = profile.bioExcerpt ?? null;
          }
          await sleep(300);
        }

        allCandidates.push({
          full_name: c.name,
          political_office: chamberConfig.office,
          governance_level: chamberConfig.governance_level,
          state: stateAbbr,
          district: district ?? null,
          party_affiliation: normaliseParty(c.party),
          election_date: `${ELECTION_YEAR}-11-03`,
          is_running_candidate: c.result_status == null,
          result_status: c.result_status ?? null,
          ballotpedia_url: c.ballotpedia_url ?? raceUrl,
          campaign_website: campaignWebsite,
          bio_excerpt: bioExcerpt,
          source_page: raceUrl,
          scraped_at: new Date().toISOString(),
        });
      }

      console.log(`  ✓ ${stateAbbr} ${district ?? chamberConfig.key} — ${candidates.length} candidate(s)`);
      await sleep(DELAY_MS);
    }

    // If any offices need the index fallback, run them now
    if (fallbackIndexes.length > 0) {
      console.log('\n[direct→index fallback for offices without direct templates]');
      await runIndexStrategy(fallbackIndexes, newPage, allCandidates);
    }

  } else if (STRATEGY === 'widget') {
    // ─────────────────────────────────────────────────────────────────────
    // WIDGET strategy: visit per-state overview pages and parse the
    // bp-table widget tables that list all offices for a state in one
    // place with data-cell attributes for reliable extraction.
    // ─────────────────────────────────────────────────────────────────────
    await runWidgetStrategy(newPage, allCandidates);
  } else {
    // ─────────────────────────────────────────────────────────────────────
    // INDEX strategy (original): visit the central index page per office,
    // collect all race links, then visit each.
    // ─────────────────────────────────────────────────────────────────────
    await runIndexStrategy(indexes, newPage, allCandidates);
  }

  await browser.close();

  // ── Deduplicate: same name + state + office ───────────────────────────────
  const seen = new Set();
  const deduped = allCandidates.filter(c => {
    const key = `${c.full_name?.toLowerCase()}|${c.state}|${c.political_office}`;
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });

  // Write output
  mkdirSync(dirname(OUT_PATH), { recursive: true });
  writeFileSync(OUT_PATH, JSON.stringify(deduped, null, 2), 'utf8');

  console.log(`\n✓ Scraped ${deduped.length} unique candidates → ${OUT_PATH}`);
  console.log('\nNext step:');
  console.log(`  php artisan politicians:import-ballotpedia --file=${OUT_PATH}`);
}

main().catch(err => {
  console.error('\nFatal error:', err);
  process.exit(1);
});
