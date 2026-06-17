/**
 * 50-State Local Voter Guide Scraper
 *
 * Visits the authoritative local voter-guide or election-results page for each
 * U.S. state and extracts candidate names, offices, and (where available)
 * election result status.  Output is a JSON array compatible with:
 *
 *   php artisan politicians:import-ballotpedia --file=<out>
 *   php artisan politicians:import-election-results --file=<out>
 *
 * Usage:
 *   node scripts/scrape-state-voter-guides.js [options]
 *
 * Options:
 *   --state=CA           Only scrape one state (two-letter code)
 *   --states=CA,TX,NY    Comma-separated list of states to scrape
 *   --year=2026          Election year (default: 2026)
 *   --out=path/to/out.json
 *   --timeout=30000      Per-page timeout in ms (default: 30000)
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

// ── CLI args ──────────────────────────────────────────────────────────────────

const args = Object.fromEntries(
  process.argv.slice(2)
    .filter(a => a.startsWith('--'))
    .map(a => {
      const [k, ...rest] = a.slice(2).split('=');
      return [k, rest.join('=') || 'true'];
    })
);

const ELECTION_YEAR  = args.year ? parseInt(args.year, 10) : 2026;
const PAGE_TIMEOUT   = args.timeout ? parseInt(args.timeout, 10) : 30_000;
const OUT_PATH       = args.out
  ? resolve(process.cwd(), args.out)
  : resolve(__dirname, `../storage/app/imports/state-voter-guides-${ELECTION_YEAR}.json`);

// Resolve which states to scrape
let STATE_FILTER = null;
if (args.state) {
  STATE_FILTER = [args.state.toUpperCase()];
} else if (args.states) {
  STATE_FILTER = args.states.toUpperCase().split(',').map(s => s.trim()).filter(Boolean);
}

const DELAY_MS = 1200;

function sleep(ms) {
  return new Promise(r => setTimeout(r, ms));
}

// ── Per-state voter guide / election-results URLs ─────────────────────────────
//
// Priority order for each state:
//  1. State-specific nonpartisan voter guide (e.g. CalMatters, Colorado Sun)
//  2. State secretary-of-state official results page
//  3. Ballotpedia state overview (fallback)
//
// Each entry may have multiple `urls` tried in order until one yields results.
// `scraper` controls which extraction strategy to use:
//   'generic'  — generic link/table walk (default)
//   'calmatters' — CalMatters 2026 voter guide structure
//   'sos'        — Secretary-of-state results table
//   'ballotpedia'— Ballotpedia state summary page
// ─────────────────────────────────────────────────────────────────────────────
const STATE_SOURCES = {
  AL: {
    name: 'Alabama',
    urls: [
      `https://ballotpedia.org/Alabama_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  AK: {
    name: 'Alaska',
    urls: [
      `https://ballotpedia.org/Alaska_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  AZ: {
    name: 'Arizona',
    urls: [
      `https://azcapitoltimes.com/news/category/elections/`,
      `https://ballotpedia.org/Arizona_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  AR: {
    name: 'Arkansas',
    urls: [
      `https://ballotpedia.org/Arkansas_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  CA: {
    name: 'California',
    urls: [
      `https://calmatters.org/california-voter-guide-${ELECTION_YEAR}/`,
      `https://ballotpedia.org/California_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'calmatters',
    // Office-specific sub-pages used when scraper='calmatters'
    offices: [
      { slug: 'governor',            office: 'Governor',           governance_level: 'State' },
      { slug: 'lieutenant-governor', office: 'Lieutenant Governor', governance_level: 'State' },
      { slug: 'attorney-general',    office: 'Attorney General',    governance_level: 'State' },
      { slug: 'state-treasurer',     office: 'State Treasurer',     governance_level: 'State' },
      { slug: 'state-controller',    office: 'State Controller',    governance_level: 'State' },
      { slug: 'secretary-of-state',  office: 'Secretary of State',  governance_level: 'State' },
      { slug: 'us-senate',           office: 'U.S. Senator',        governance_level: 'Federal' },
    ],
  },
  CO: {
    name: 'Colorado',
    urls: [
      `https://coloradosun.com/tag/colorado-elections-${ELECTION_YEAR}/`,
      `https://ballotpedia.org/Colorado_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  CT: {
    name: 'Connecticut',
    urls: [
      `https://ctmirror.org/category/all-news/politics/elections/`,
      `https://ballotpedia.org/Connecticut_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  DE: {
    name: 'Delaware',
    urls: [
      `https://ballotpedia.org/Delaware_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  FL: {
    name: 'Florida',
    urls: [
      `https://www.tampabay.com/news/florida-politics/elections/`,
      `https://ballotpedia.org/Florida_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  GA: {
    name: 'Georgia',
    urls: [
      `https://www.ajc.com/politics/georgia-elections/`,
      `https://ballotpedia.org/Georgia_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  HI: {
    name: 'Hawaii',
    urls: [
      `https://ballotpedia.org/Hawaii_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  ID: {
    name: 'Idaho',
    urls: [
      `https://ballotpedia.org/Idaho_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  IL: {
    name: 'Illinois',
    urls: [
      `https://capitolfax.com/`,
      `https://ballotpedia.org/Illinois_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  IN: {
    name: 'Indiana',
    urls: [
      `https://indianacapitalchronicle.com/category/elections/`,
      `https://ballotpedia.org/Indiana_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  IA: {
    name: 'Iowa',
    urls: [
      `https://iowacapitaldispatch.com/category/elections/`,
      `https://ballotpedia.org/Iowa_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  KS: {
    name: 'Kansas',
    urls: [
      `https://kansasreflector.com/category/elections/`,
      `https://ballotpedia.org/Kansas_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  KY: {
    name: 'Kentucky',
    urls: [
      `https://kentuckylantern.com/category/politics/elections/`,
      `https://ballotpedia.org/Kentucky_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  LA: {
    name: 'Louisiana',
    urls: [
      `https://lailluminator.com/category/elections/`,
      `https://ballotpedia.org/Louisiana_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  ME: {
    name: 'Maine',
    urls: [
      `https://mainepublic.org/politics`,
      `https://ballotpedia.org/Maine_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  MD: {
    name: 'Maryland',
    urls: [
      `https://marylandmatters.org/category/elections/`,
      `https://ballotpedia.org/Maryland_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  MA: {
    name: 'Massachusetts',
    urls: [
      `https://commonwealthbeacon.org/category/politics/elections/`,
      `https://ballotpedia.org/Massachusetts_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  MI: {
    name: 'Michigan',
    urls: [
      `https://www.bridgemi.com/michigan-government/elections`,
      `https://ballotpedia.org/Michigan_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  MN: {
    name: 'Minnesota',
    urls: [
      `https://www.minnpost.com/category/election-2026/`,
      `https://ballotpedia.org/Minnesota_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  MS: {
    name: 'Mississippi',
    urls: [
      `https://mississippitoday.org/category/elections/`,
      `https://ballotpedia.org/Mississippi_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  MO: {
    name: 'Missouri',
    urls: [
      `https://missouriindependent.com/category/elections/`,
      `https://ballotpedia.org/Missouri_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  MT: {
    name: 'Montana',
    urls: [
      `https://montanafreepress.org/category/politics/elections/`,
      `https://ballotpedia.org/Montana_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  NE: {
    name: 'Nebraska',
    urls: [
      `https://nebraskaexaminer.com/category/elections/`,
      `https://ballotpedia.org/Nebraska_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  NV: {
    name: 'Nevada',
    urls: [
      `https://thenevadaindependent.com/elections`,
      `https://ballotpedia.org/Nevada_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  NH: {
    name: 'New Hampshire',
    urls: [
      `https://www.nhjournal.com/category/politics/elections/`,
      `https://ballotpedia.org/New_Hampshire_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  NJ: {
    name: 'New Jersey',
    urls: [
      `https://www.njspotlightnews.org/topic/elections/`,
      `https://ballotpedia.org/New_Jersey_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  NM: {
    name: 'New Mexico',
    urls: [
      `https://nmpoliticalreport.com/category/elections/`,
      `https://ballotpedia.org/New_Mexico_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  NY: {
    name: 'New York',
    urls: [
      `https://www.cityandstateny.com/elections`,
      `https://ballotpedia.org/New_York_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  NC: {
    name: 'North Carolina',
    urls: [
      `https://www.nc99.org/`,
      `https://ballotpedia.org/North_Carolina_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  ND: {
    name: 'North Dakota',
    urls: [
      `https://ballotpedia.org/North_Dakota_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  OH: {
    name: 'Ohio',
    urls: [
      `https://ohiocapitaljournal.com/category/elections/`,
      `https://ballotpedia.org/Ohio_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  OK: {
    name: 'Oklahoma',
    urls: [
      `https://oklahomawatch.org/category/elections/`,
      `https://ballotpedia.org/Oklahoma_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  OR: {
    name: 'Oregon',
    urls: [
      `https://www.opb.org/topic/politics-elections/`,
      `https://ballotpedia.org/Oregon_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  PA: {
    name: 'Pennsylvania',
    urls: [
      `https://www.spotlightpa.org/topics/elections/`,
      `https://ballotpedia.org/Pennsylvania_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  RI: {
    name: 'Rhode Island',
    urls: [
      `https://thePublicsSRadio.org/topic/rhode-island-politics/`,
      `https://ballotpedia.org/Rhode_Island_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  SC: {
    name: 'South Carolina',
    urls: [
      `https://southcarolinasuntimes.com/category/elections/`,
      `https://ballotpedia.org/South_Carolina_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  SD: {
    name: 'South Dakota',
    urls: [
      `https://sdnewswatch.org/category/elections/`,
      `https://ballotpedia.org/South_Dakota_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  TN: {
    name: 'Tennessee',
    urls: [
      `https://tennesseelookout.com/category/elections/`,
      `https://ballotpedia.org/Tennessee_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  TX: {
    name: 'Texas',
    urls: [
      `https://www.texastribune.org/series/2026-texas-elections/`,
      `https://ballotpedia.org/Texas_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  UT: {
    name: 'Utah',
    urls: [
      `https://www.sltrib.com/news/politics/`,
      `https://ballotpedia.org/Utah_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  VT: {
    name: 'Vermont',
    urls: [
      `https://vtdigger.org/category/politics/elections/`,
      `https://ballotpedia.org/Vermont_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  VA: {
    name: 'Virginia',
    urls: [
      `https://virginiamercury.com/category/elections/`,
      `https://ballotpedia.org/Virginia_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  WA: {
    name: 'Washington',
    urls: [
      `https://crosscut.com/politics`,
      `https://ballotpedia.org/Washington_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  WV: {
    name: 'West Virginia',
    urls: [
      `https://wvpublic.org/category/news/west-virginia-politics/`,
      `https://ballotpedia.org/West_Virginia_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  WI: {
    name: 'Wisconsin',
    urls: [
      `https://wispolitics.com/`,
      `https://ballotpedia.org/Wisconsin_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
  WY: {
    name: 'Wyoming',
    urls: [
      `https://wyofile.com/category/politics/elections/`,
      `https://ballotpedia.org/Wyoming_elections,_${ELECTION_YEAR}`,
    ],
    scraper: 'ballotpedia',
  },
};

// ── Normalise party name ──────────────────────────────────────────────────────

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

// ── Scraper: CalMatters voter guide ──────────────────────────────────────────
//
// CalMatters publishes per-office voter guide sub-pages under:
//   https://calmatters.org/california-voter-guide-YEAR/<office-slug>/
//
// Each page embeds an interactive iFrame (project-voter-guide-YEAR.interactives.calmatters.org)
// that lists candidates. We fall back to scraping article-style candidate cards
// from the main CalMatters page if the iframe fails to load.

async function scrapeCalMatters(browser, stateCode, config, year) {
  const results = [];
  const offices = config.offices ?? [];

  for (const officeConfig of offices) {
    const url = `https://calmatters.org/california-voter-guide-${year}/${officeConfig.slug}/`;
    console.log(`    CalMatters [${officeConfig.office}] → ${url}`);

    const ctx = await browser.newContext({
      userAgent: 'Mozilla/5.0 (compatible; U9itus-civic-bot/1.0; +https://u9itus.dev/about)',
      locale: 'en-US',
    });
    const page = await ctx.newPage();

    try {
      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: PAGE_TIMEOUT });
      await sleep(DELAY_MS);

      // Attempt 1: look for candidate cards / article links that contain names
      const candidates = await page.evaluate((args) => {
        const { office, governanceLevel, stateCode, year } = args;
        const found = [];

        // Candidate name patterns in CalMatters:
        // <h2 class="candidate-name"> or <strong> inside .candidate-card
        const nameSelectors = [
          '.candidate-name', '.candidate-card h2', '.candidate-card h3',
          '.candidate-card strong', '[class*="candidate"] h2',
          '[class*="candidate"] h3', '[class*="candidate"] strong',
        ];

        for (const sel of nameSelectors) {
          const els = document.querySelectorAll(sel);
          for (const el of els) {
            const name = (el.textContent ?? '').trim().replace(/\s+/g, ' ');
            if (name.length < 3) continue;

            // Attempt to find party in sibling/parent
            const parent = el.closest('[class*="candidate"]') ?? el.parentElement;
            const parentText = (parent?.textContent ?? '').trim();
            let party = null;
            const partyMatch = parentText.match(/\b(Democrat|Republican|Libertarian|Green|Independent|No Party Preference|DEM|REP|LIB)\b/i);
            if (partyMatch) party = partyMatch[1];

            found.push({ name, party });
          }
        }

        // Attempt 2: look for structured article list items
        if (found.length === 0) {
          const listItems = document.querySelectorAll('article h2, article h3, .entry-title');
          for (const el of listItems) {
            const name = (el.textContent ?? '').trim().replace(/\s+/g, ' ');
            // Filter: candidate names are typically 2-4 words, no punctuation
            if (name.length < 4 || name.length > 60) continue;
            if (/\bguide\b|\bvoter\b|\bcandidate\b|\belection\b/i.test(name)) continue;
            found.push({ name, party: null });
          }
        }

        return found.map(c => ({
          full_name: c.name,
          political_office: office,
          governance_level: governanceLevel,
          state: stateCode,
          party_affiliation: c.party,
          election_date: `${year}-11-03`,
          is_running_candidate: true,
          result_status: null,
          source_url: window.location.href,
          scraped_at: new Date().toISOString(),
        }));
      }, { office: officeConfig.office, governanceLevel: officeConfig.governance_level, stateCode, year });

      if (candidates.length > 0) {
        console.log(`      → ${candidates.length} candidate(s) found`);
        results.push(...candidates);
      } else {
        console.log(`      → No candidates extracted from CalMatters page; skipping office.`);
      }
    } catch (err) {
      console.warn(`      ✗ CalMatters scrape failed for ${officeConfig.office}: ${err.message}`);
    } finally {
      await ctx.close();
    }

    await sleep(DELAY_MS);
  }

  return results;
}

// ── Scraper: Ballotpedia state overview ───────────────────────────────────────
//
// Ballotpedia state election overview pages list links to individual race pages.
// We extract candidate links directly from those race links to build a lightweight
// candidate list (names only — no need to deep-scrape every race page here;
// scrape-ballotpedia.js handles the deep scrape).

async function scrapeBallotpediaState(browser, stateCode, stateConfig) {
  const stateUrl = stateConfig.urls.find(u => u.includes('ballotpedia.org'));
  if (!stateUrl) return [];

  const ctx = await browser.newContext({
    userAgent: 'Mozilla/5.0 (compatible; U9itus-civic-bot/1.0; +https://u9itus.dev/about)',
    locale: 'en-US',
  });
  const page = await ctx.newPage();

  try {
    console.log(`    Ballotpedia state overview → ${stateUrl}`);
    await page.goto(stateUrl, { waitUntil: 'domcontentloaded', timeout: PAGE_TIMEOUT });
    await sleep(DELAY_MS);

    const candidates = await page.evaluate((args) => {
      const { stateCode, year } = args;
      const found = [];

      // Ballotpedia state overview pages list office sections with candidate tables
      // Structure: <h2> Office Name </h2> ... <table> ... <tr> Name | Party | Result </tr>
      const headings = document.querySelectorAll('h2, h3');

      for (const heading of headings) {
        const headingText = (heading.textContent ?? '').toLowerCase().trim();
        if (!headingText) continue;

        // Identify likely office sections
        let office = null;
        let governanceLevel = 'State';
        if (/governor/i.test(headingText) && !/lt|lieutenant/i.test(headingText)) { office = 'Governor'; }
        else if (/lieutenant governor/i.test(headingText)) { office = 'Lieutenant Governor'; }
        else if (/attorney general/i.test(headingText)) { office = 'Attorney General'; }
        else if (/treasurer/i.test(headingText)) { office = 'State Treasurer'; }
        else if (/secretary of state/i.test(headingText)) { office = 'Secretary of State'; }
        else if (/controller/i.test(headingText)) { office = 'State Controller'; }
        else if (/u\.s\. senate|united states senate/i.test(headingText)) { office = 'U.S. Senator'; governanceLevel = 'Federal'; }
        else if (/u\.s\. house|congressional/i.test(headingText)) { office = 'U.S. Representative'; governanceLevel = 'Federal'; }

        if (!office) continue;

        // Walk next siblings until the next heading
        let sibling = heading.nextElementSibling;
        while (sibling && !['H2', 'H3'].includes(sibling.tagName)) {
          if (sibling.tagName === 'TABLE') {
            const rows = sibling.querySelectorAll('tr');
            for (const row of rows) {
              const cells = row.querySelectorAll('td');
              if (cells.length < 1) continue;
              const nameCell = cells[0]?.textContent?.trim() ?? '';
              if (nameCell.length < 3 || /candidate|name/i.test(nameCell)) continue;

              const partyCell = cells[1]?.textContent?.trim() ?? null;
              const resultCell = cells[cells.length - 1]?.textContent?.trim().toLowerCase() ?? '';

              let resultStatus = null;
              if (/won|elected|advanced|\u2713|\u2714/i.test(resultCell)) resultStatus = 'won';
              else if (/lost|defeated|eliminated/i.test(resultCell)) resultStatus = 'lost';

              found.push({
                full_name: nameCell.replace(/\s+/g, ' ').trim(),
                political_office: office,
                governance_level: governanceLevel,
                state: stateCode,
                party_affiliation: partyCell,
                election_date: `${year}-11-03`,
                is_running_candidate: resultStatus == null,
                result_status: resultStatus,
                source_url: window.location.href,
                scraped_at: new Date().toISOString(),
              });
            }
          }
          sibling = sibling.nextElementSibling;
        }
      }

      return found;
    }, { stateCode, year: ELECTION_YEAR });

    if (candidates.length > 0) {
      console.log(`    → ${candidates.length} candidate(s) found`);
    } else {
      console.log(`    → No candidates extracted from Ballotpedia state overview.`);
    }
    return candidates;
  } catch (err) {
    console.warn(`    ✗ Ballotpedia state scrape failed for ${stateCode}: ${err.message}`);
    return [];
  } finally {
    await ctx.close();
  }
}

// ── Local news scraper (generic) ──────────────────────────────────────────────
//
// For state sources with scraper='generic', we attempt a best-effort extract of
// candidate names from article headlines and structured lists.

async function scrapeLocalNewsGeneric(browser, stateCode, stateConfig) {
  const [primaryUrl] = stateConfig.urls;
  const ctx = await browser.newContext({
    userAgent: 'Mozilla/5.0 (compatible; U9itus-civic-bot/1.0; +https://u9itus.dev/about)',
    locale: 'en-US',
  });
  const page = await ctx.newPage();

  try {
    console.log(`    Local news → ${primaryUrl}`);
    await page.goto(primaryUrl, { waitUntil: 'domcontentloaded', timeout: PAGE_TIMEOUT });
    await sleep(DELAY_MS);

    const candidates = await page.evaluate((args) => {
      const { stateCode, year } = args;
      const found = [];

      // Generic: scrape article titles that mention candidate names + offices
      const headlineEls = document.querySelectorAll('h1, h2, h3, article h4');
      const officePatterns = [
        { re: /governor/i, office: 'Governor', level: 'State' },
        { re: /lieutenant governor/i, office: 'Lieutenant Governor', level: 'State' },
        { re: /attorney general/i, office: 'Attorney General', level: 'State' },
        { re: /u\.s\. senate|united states senate/i, office: 'U.S. Senator', level: 'Federal' },
        { re: /u\.s\. house|congress/i, office: 'U.S. Representative', level: 'Federal' },
        { re: /state treasurer/i, office: 'State Treasurer', level: 'State' },
        { re: /secretary of state/i, office: 'Secretary of State', level: 'State' },
      ];

      for (const el of headlineEls) {
        const text = (el.textContent ?? '').trim().replace(/\s+/g, ' ');
        for (const { re, office, level } of officePatterns) {
          if (re.test(text)) {
            // Extract candidate name: look for "Name wins/loses/advances/enters" pattern
            const nameMatch = text.match(/^([A-Z][a-z]+ (?:[A-Z][a-z]+ )?[A-Z][a-z]+)/);
            if (nameMatch) {
              let resultStatus = null;
              if (/wins|elected|advances|victor/i.test(text)) resultStatus = 'won';
              else if (/loses|defeated|drops out/i.test(text)) resultStatus = 'lost';
              found.push({
                full_name: nameMatch[1],
                political_office: office,
                governance_level: level,
                state: stateCode,
                party_affiliation: null,
                election_date: `${year}-11-03`,
                is_running_candidate: resultStatus == null,
                result_status: resultStatus,
                source_url: window.location.href,
                scraped_at: new Date().toISOString(),
              });
            }
          }
        }
      }

      return found;
    }, { stateCode, year: ELECTION_YEAR });

    if (candidates.length > 0) {
      console.log(`    → ${candidates.length} candidate(s) found from local news`);
    }
    return candidates;
  } catch (err) {
    console.warn(`    ✗ Local news scrape failed for ${stateCode}: ${err.message}`);
    return [];
  } finally {
    await ctx.close();
  }
}

// ── Main ──────────────────────────────────────────────────────────────────────

async function main() {
  const statesToScrape = STATE_FILTER
    ? Object.entries(STATE_SOURCES).filter(([code]) => STATE_FILTER.includes(code))
    : Object.entries(STATE_SOURCES);

  console.log(`\n50-State Voter Guide Scraper — ${ELECTION_YEAR}`);
  console.log(`States   : ${statesToScrape.map(([c]) => c).join(', ')}`);
  console.log(`Output   : ${OUT_PATH}\n`);

  const browser = await chromium.launch({ headless: true });
  const allCandidates = [];

  for (const [stateCode, stateConfig] of statesToScrape) {
    console.log(`\n[${stateCode}] ${stateConfig.name}`);

    let candidates = [];

    if (stateConfig.scraper === 'calmatters') {
      // CA: scrape CalMatters office sub-pages first, then fall back to Ballotpedia
      candidates = await scrapeCalMatters(browser, stateCode, stateConfig, ELECTION_YEAR);
      if (candidates.length === 0) {
        candidates = await scrapeBallotpediaState(browser, stateCode, stateConfig);
      }
    } else {
      // All other states: try local news (generic), then Ballotpedia overview
      const localCandidates = await scrapeLocalNewsGeneric(browser, stateCode, stateConfig);
      const bpCandidates    = await scrapeBallotpediaState(browser, stateCode, stateConfig);

      // Merge: prefer local news entries that match Ballotpedia names, otherwise union
      const bpNames = new Set(bpCandidates.map(c => c.full_name.toLowerCase()));
      candidates = [
        ...bpCandidates,
        ...localCandidates.filter(c => !bpNames.has(c.full_name.toLowerCase())),
      ];
    }

    // Normalise party names
    candidates = candidates.map(c => ({
      ...c,
      party_affiliation: normaliseParty(c.party_affiliation),
    }));

    allCandidates.push(...candidates);
    console.log(`  ✓ ${stateCode}: ${candidates.length} candidate(s) collected`);

    await sleep(500);
  }

  await browser.close();

  // Deduplicate: same name + state + office
  const seen = new Set();
  const deduped = allCandidates.filter(c => {
    const key = `${(c.full_name ?? '').toLowerCase()}|${c.state}|${c.political_office}`;
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });

  mkdirSync(dirname(OUT_PATH), { recursive: true });
  writeFileSync(OUT_PATH, JSON.stringify(deduped, null, 2), 'utf8');

  const wonCount  = deduped.filter(c => c.result_status === 'won').length;
  const lostCount = deduped.filter(c => c.result_status === 'lost').length;

  console.log(`\n✓ Total: ${deduped.length} unique candidates → ${OUT_PATH}`);
  console.log(`  winners: ${wonCount}  |  losers: ${lostCount}  |  pending: ${deduped.length - wonCount - lostCount}`);
  console.log('\nNext step:');
  console.log(`  php artisan politicians:import-election-results --file=${OUT_PATH}`);
}

main().catch(err => {
  console.error('\nFatal error:', err);
  process.exit(1);
});
