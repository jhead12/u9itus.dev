/**
 * Ballotpedia 2026 Election Candidate Scraper
 *
 * Scrapes primary election results from Ballotpedia for the 2026 U.S. House,
 * Senate, and statewide executive offices, outputting a JSON array compatible with:
 *   php artisan politicians:import-ballotpedia --file=storage/app/imports/ballotpedia-2026.json
 *
 * Usage:
 *   node scripts/scrape-ballotpedia.js [--office=<filter>] [--state=CA] [--out=path/to/output.json]
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
import { writeFileSync, mkdirSync } from 'fs';
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
  senate:          (s, y) => `https://ballotpedia.org/${s}_Senate_election,_${y}`,
  // House is multi-district per state — index strategy is required for House.
};

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
const DELAY_MS = 800;

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

      const racePage = await newPage();
      let raceData;
      try {
        raceData = await scrapeRacePage(racePage, raceUrl, chamberConfig.key, WITH_RESULTS);
      } catch (err) {
        console.warn(`  ✗ Skipped ${raceUrl}: ${err.message}`);
        await racePage.context().close();
        continue;
      }
      await racePage.context().close();

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
        const full = href.startsWith('http') ? href : 'https://ballotpedia.org' + href;
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
 * Scrape candidates from a single race page (e.g. a district election page).
 * When withResults=true, also captures elected/defeated/incumbent indicators.
 * Returns an array of candidate objects.
 */
async function scrapeRacePage(page, raceUrl, chamber, withResults = false) {
  await page.goto(raceUrl, { waitUntil: 'domcontentloaded', timeout: 30_000 });
  await sleep(DELAY_MS);

  return page.evaluate((args) => {
    const { raceUrl, ELECTION_YEAR, withResults } = args;
    const results = [];

    const titleEl = document.querySelector('h1#firstHeading') ?? document.querySelector('h1.firstHeading');
    const pageTitle = (titleEl?.textContent ?? '').trim();

    /**
     * Attempt 1: Parse structured candidate tables (Ballotpedia's standard format).
     * These are usually <table> elements in sections titled "Candidates" or "Primary candidates".
     */
    const sections = document.querySelectorAll('h2, h3');
    let inCandidateSection = false;

    for (const heading of sections) {
      const headingText = (heading.textContent ?? '').toLowerCase();
      inCandidateSection = headingText.includes('candidate') || headingText.includes('general election');

      if (!inCandidateSection) continue;

      // Walk siblings until next heading
      let sibling = heading.nextElementSibling;
      while (sibling && !['H2', 'H3'].includes(sibling.tagName)) {
        if (sibling.tagName === 'TABLE') {
          const rows = sibling.querySelectorAll('tr');
          for (const row of rows) {
            const cells = row.querySelectorAll('td');
            if (cells.length < 2) continue;

            // Typical layout: | photo? | Name | Party | Status/Note |
            const nameCell = cells[0]?.textContent?.trim() ?? '';
            const partyCell = cells[1]?.textContent?.trim() ?? '';

            if (nameCell.length < 2 || nameCell.toLowerCase().includes('candidate')) continue;

            // Find the first anchor that is a genuine Ballotpedia profile page.
            // Use a strict WHITELIST: only accept root-relative paths (/Page_Name)
            // or explicit https://ballotpedia.org/ URLs with no query string.
            // This is intentionally narrow — anything that doesn't look exactly
            // like an internal Ballotpedia link is dropped, preventing survey/
            // mailto/campaign URLs from ever reaching the database.
            // ── Result status (winner / loser / incumbent) ─────────────────
            let resultStatus = null;
            if (withResults) {
              const rowText = row.textContent ?? '';
              // Ballotpedia uses checkmarks, "Won", "Elected", "Advanced" for winners
              if (/✓|✔|won|elected|advanced|winner/i.test(rowText)) resultStatus = 'won';
              else if (/lost|defeated|eliminated/i.test(rowText)) resultStatus = 'lost';
              // Incumbent column
              const incumbentCell = Array.from(cells).find(c => /incumbent/i.test(c.textContent ?? ''));
              if (incumbentCell && !/no/i.test(incumbentCell.textContent ?? '')) resultStatus = resultStatus ?? 'incumbent';
              // Result column — last cell often holds "Won" / "Lost"
              const lastCell = cells[cells.length - 1];
              if (!resultStatus && lastCell) {
                const lt = (lastCell.textContent ?? '').trim().toLowerCase();
                if (lt === 'won' || lt === 'elected' || lt === 'advanced') resultStatus = 'won';
                else if (lt === 'lost' || lt === 'defeated') resultStatus = 'lost';
              }
            }

            const cellAnchors = Array.from(cells[0]?.querySelectorAll('a[href]') ?? []);
            const profileAnchor = cellAnchors.find(a => {
              const h = a.getAttribute('href') ?? '';
              // Must be either a root-relative path or an explicit ballotpedia.org URL
              const isInternal =
                (h.startsWith('/') && !h.startsWith('//'))       // e.g. /Dale_Strong
                || h.startsWith('https://ballotpedia.org/');     // e.g. full URL
              if (!isInternal) return false;
              // No query strings or fragment anchors
              if (h.includes('?') || h.includes('#') || h.includes('%3F') || h.includes('%23')) return false;
              return h.length > 2;
            });
            const ballotpediaLink = profileAnchor
              ? (profileAnchor.href.startsWith('https://ballotpedia.org/')
                  ? profileAnchor.href
                  : 'https://ballotpedia.org' + profileAnchor.getAttribute('href'))
              : null;

            results.push({
              name: nameCell.replace(/\s+/g, ' ').trim(),
              party: partyCell,
              ballotpedia_url: ballotpediaLink,
              result_status: resultStatus,
              page_title: pageTitle,
              election_year: ELECTION_YEAR,
              source_url: raceUrl,
            });
          }
        }

        // Also parse infobox-style candidate lists (<ul> items)
        if (sibling.tagName === 'UL') {
          for (const li of sibling.querySelectorAll('li')) {
            const text = li.textContent?.trim() ?? '';
            const link = li.querySelector('a[href]');
            if (text.length < 3) continue;

            // Extract name (before " (Party)" pattern)
            const partyMatch = text.match(/\(([^)]+)\)$/);
            const name = partyMatch ? text.slice(0, text.lastIndexOf('(')).trim() : text;
            const party = partyMatch ? partyMatch[1] : null;

            if (name.length < 2) continue;

            // Strict whitelist: only root-relative paths or explicit ballotpedia.org URLs,
            // no query strings (literal or percent-encoded), no fragments.
            const rawHref = link ? (link.getAttribute('href') ?? '') : '';
            const isValidBpLink = rawHref.length > 2
              && (
                (rawHref.startsWith('/') && !rawHref.startsWith('//'))   // /Page_Name
                || rawHref.startsWith('https://ballotpedia.org/')        // full URL
              )
              && !rawHref.includes('?')
              && !rawHref.includes('#')
              && !rawHref.includes('%3F')
              && !rawHref.includes('%23');
            const resolvedBpUrl = isValidBpLink
              ? (rawHref.startsWith('https://') ? rawHref : 'https://ballotpedia.org' + rawHref)
              : null;

            results.push({
              name,
              party,
              ballotpedia_url: resolvedBpUrl,
              page_title: pageTitle,
              election_year: ELECTION_YEAR,
              source_url: raceUrl,
            });
          }
        }

        sibling = sibling.nextElementSibling;
      }
    }

    return { pageTitle, candidates: results };
  }, { raceUrl, ELECTION_YEAR, withResults });
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
  // DIRECT strategy: construct per-state URLs from the known BP pattern.
  // Works for all statewide offices. Falls back to index for House (multi-
  // district) and for any office without a direct URL template.
  // ─────────────────────────────────────────────────────────────────────────
  if (STRATEGY === 'direct') {
    // Partition: offices with a direct template vs those that need index mode
    const directIndexes  = indexes.filter(e => e.group !== 'federal' || e.key !== 'house');
    const fallbackIndexes = indexes.filter(e => !DIRECT_URL_TEMPLATES[e.key]);

    if (fallbackIndexes.length > 0) {
      console.log(`  [direct] Offices without a direct template (will use index): ${fallbackIndexes.map(e => e.key).join(', ')}`);
    }

    const directRaces = buildDirectRaceList(directIndexes);
    console.log(`  [direct] ${directRaces.length} race URLs to visit across all states.\n`);

    for (const { url: raceUrl, stateAbbr, stateName, chamberConfig } of directRaces) {
      const racePage = await newPage();
      let raceData;
      try {
        raceData = await scrapeRacePage(racePage, raceUrl, chamberConfig.key, WITH_RESULTS);
      } catch (err) {
        // A 404 means this state simply doesn't have this office (e.g. TX has no Lt. Gov.
        // in the same pattern) — silently skip rather than warn.
        if (!err.message.includes('404') && !err.message.includes('net::ERR')) {
          console.warn(`  ✗ ${stateAbbr} ${chamberConfig.key}: ${err.message}`);
        }
        await racePage.context().close();
        continue;
      }
      await racePage.context().close();

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
          district: null,
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

      console.log(`  ✓ ${stateAbbr} ${chamberConfig.key} — ${candidates.length} candidate(s)`);
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
