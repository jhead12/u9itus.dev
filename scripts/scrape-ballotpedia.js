/**
 * Ballotpedia 2026 Election Candidate Scraper
 *
 * Scrapes primary election results from Ballotpedia for the 2026 U.S. House and
 * Senate elections and outputs a JSON array compatible with:
 *   php artisan politicians:import-ballotpedia --file=storage/app/imports/ballotpedia-2026.json
 *
 * Usage:
 *   node scripts/scrape-ballotpedia.js [--office=house|senate|all] [--state=CA] [--out=path/to/output.json]
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
      const [k, v = 'true'] = a.slice(2).split('=');
      return [k, v];
    })
);

const OFFICE_FILTER = (args.office ?? 'all').toLowerCase();
const STATE_FILTER  = args.state ? args.state.toUpperCase() : null;
const OUT_PATH      = args.out
  ? resolve(process.cwd(), args.out)
  : resolve(__dirname, '../storage/app/imports/ballotpedia-2026.json');

const ELECTION_YEAR = 2026;

// Ballotpedia index pages for each chamber
const ELECTION_INDEXES = [
  {
    key: 'house',
    url: `https://ballotpedia.org/United_States_House_of_Representatives_elections,_${ELECTION_YEAR}`,
    office: 'U.S. Representative',
    governance_level: 'Federal',
  },
  {
    key: 'senate',
    url: `https://ballotpedia.org/United_States_Senate_elections,_${ELECTION_YEAR}`,
    office: 'U.S. Senator',
    governance_level: 'Federal',
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
 * Returns an array of candidate objects.
 */
async function scrapeRacePage(page, raceUrl, chamber) {
  await page.goto(raceUrl, { waitUntil: 'domcontentloaded', timeout: 30_000 });
  await sleep(DELAY_MS);

  return page.evaluate((args) => {
    const { raceUrl, ELECTION_YEAR } = args;
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

            const ballotpediaLink = cells[0]?.querySelector('a[href]')?.href ?? raceUrl;

            results.push({
              name: nameCell.replace(/\s+/g, ' ').trim(),
              party: partyCell,
              ballotpedia_url: ballotpediaLink,
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

            results.push({
              name,
              party,
              ballotpedia_url: link ? (link.href.startsWith('http') ? link.href : 'https://ballotpedia.org' + link.getAttribute('href')) : raceUrl,
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
  }, { raceUrl, ELECTION_YEAR });
}

async function main() {
  const indexes = ELECTION_INDEXES.filter(e =>
    OFFICE_FILTER === 'all' || e.key === OFFICE_FILTER
  );

  if (indexes.length === 0) {
    console.error(`Unknown --office value: ${OFFICE_FILTER}. Use house, senate, or all.`);
    process.exit(1);
  }

  console.log(`\nBallotpedia ${ELECTION_YEAR} scraper`);
  console.log(`Chambers : ${indexes.map(e => e.key).join(', ')}`);
  console.log(`State    : ${STATE_FILTER ?? 'all'}`);
  console.log(`Output   : ${OUT_PATH}\n`);

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    userAgent: 'Mozilla/5.0 (compatible; U9itus-civic-bot/1.0; +https://u9itus.dev/about)',
    locale: 'en-US',
  });
  const page = await context.newPage();

  const allCandidates = [];

  for (const chamberConfig of indexes) {
    console.log(`\n[${ chamberConfig.key.toUpperCase() }] Scraping index page…`);

    let raceLinks;
    try {
      raceLinks = await scrapeRaceLinks(page, chamberConfig.url, chamberConfig.key);
    } catch (err) {
      console.error(`  ✗ Failed to scrape index: ${err.message}`);
      continue;
    }

    console.log(`  Found ${raceLinks.length} race pages.`);

    for (const { url: raceUrl, text: raceText } of raceLinks) {
      // Parse state from the link text or URL slug
      const slugText = decodeURIComponent(raceUrl.split('/').pop() ?? '').replace(/_/g, ' ');
      const stateAbbr = parseStateFromTitle(raceText || slugText);

      if (STATE_FILTER && stateAbbr !== STATE_FILTER) continue;

      let raceData;
      try {
        raceData = await scrapeRacePage(page, raceUrl, chamberConfig.key);
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

        allCandidates.push({
          full_name: fullName,
          political_office: chamberConfig.office,
          governance_level: chamberConfig.governance_level,
          state: stateAbbr ?? null,
          district: district ?? null,
          party_affiliation: normaliseParty(c.party),
          election_date: `${ELECTION_YEAR}-11-03`,
          is_running_candidate: true,
          ballotpedia_url: c.ballotpedia_url ?? raceUrl,
          source_page: raceUrl,
          scraped_at: new Date().toISOString(),
        });
      }

      if (candidates.length > 0) {
        console.log(`  ✓ ${stateAbbr ?? '??'} ${district ?? chamberConfig.key} — ${candidates.length} candidate(s)`);
      }
    }
  }

  await browser.close();

  // Deduplicate: same name + state + office
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
