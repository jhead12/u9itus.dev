/**
 * OpenSecrets Candidate Finance Scraper
 *
 * OpenSecrets retired their free public API. This script scrapes the same
 * data directly from their public profile pages using Playwright.
 *
 * TWO scraping modes:
 *
 *  1. CANDIDATE mode (default) — scrapes an individual candidate's profile:
 *       https://www.opensecrets.org/profiles/{slug}/us_congress/summary?mpid={mpid}
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

const sleep = (ms) => new Promise(r => setTimeout(r, ms));

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

// ── Core scraping functions ───────────────────────────────────────────────────

/**
 * Search OpenSecrets for a candidate by name and state.
 * Returns { mpid, profileUrl, name } or null.
 */
async function searchCandidate(page, name, state) {
  const query = encodeURIComponent(name + (state ? ' ' + state : ''));
  const searchUrl = `https://www.opensecrets.org/search?q=${query}&type=politicians`;

  console.error(`  [search] ${name} ${state ?? ''} → ${searchUrl}`);

  try {
    await page.goto(searchUrl, { waitUntil: 'domcontentloaded', timeout: TIMEOUT });
    await sleep(DELAY_MS);

    // Extract search result links matching /profiles/ URLs
    const results = await page.evaluate(() => {
      return Array.from(document.querySelectorAll('a[href*="/profiles/"]'))
        .filter(a => a.href.includes('/us_congress/') || a.href.includes('/state-politics/') || a.href.includes('/governors/'))
        .map(a => ({
          text: a.textContent.trim(),
          href: a.href,
        }))
        .slice(0, 5);
    });

    if (!results.length) {
      // Fallback: try direct URL construction with name slug
      const slug = nameSlug(name);
      const directUrl = `https://www.opensecrets.org/profiles/${slug}/us_congress/summary`;
      console.error(`  [search] No results — trying direct slug: ${directUrl}`);
      return { profileUrl: directUrl, mpid: null, name };
    }

    const best = results[0];
    // Extract mpid from URL: /profiles/adam-schiff/us_congress/summary?mpid=1105090
    const mpidMatch = best.href.match(/[?&]mpid=(\d+)/);
    return {
      profileUrl: best.href,
      mpid: mpidMatch ? mpidMatch[1] : null,
      name: best.text,
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

  console.error(`  [scrape] ${summaryUrl}`);

  await page.goto(summaryUrl, { waitUntil: 'domcontentloaded', timeout: TIMEOUT });
  await sleep(DELAY_MS);

  // Check for 404 / redirect to a different person
  const title = await page.title();
  if (title.toLowerCase().includes('not found') || title.toLowerCase().includes('page not found')) {
    console.error(`  [scrape] 404 — page not found`);
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

      // Contributors: has "associated_organization" or "org"
      // OpenSecrets renders TWO tables per section: one header-only, one with data.
      // We always take the last non-empty match, so skip if rows is empty.
      if (keys.some(k => k.includes('organization') || k.includes('org'))) {
        const parsed = rows.map(r => ({
          name:        r[keys.find(k => k.includes('organization') || k.includes('org'))] ?? '',
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
  const ctx  = await browser.newContext({
    userAgent: 'Mozilla/5.0 (compatible; U9itus-civic-bot/1.0; +https://u9itus.dev/about)',
    locale: 'en-US',
  });
  const page = await ctx.newPage();

  try {
    let profileUrl = null;
    let mpid = candidate.opensecrets_mpid ?? MPID_ARG ?? null;

    // Direct mpid URL
    if (mpid) {
      const slug = nameSlug(candidate.full_name ?? candidate.name ?? '');
      profileUrl = `https://www.opensecrets.org/profiles/${slug}/us_congress/summary?mpid=${mpid}`;
    } else {
      // Search by name + state
      const found = await searchCandidate(page, candidate.full_name ?? candidate.name, candidate.state ?? STATE_ARG);
      if (!found) return null;
      profileUrl = found.profileUrl;
      mpid       = found.mpid;
    }

    await sleep(300);
    const scraped = await scrapeProfilePage(page, profileUrl);

    return scraped ? { ...scraped, mpid: mpid ?? scraped.mpid, input_name: candidate.full_name ?? candidate.name } : null;
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

  const ctx  = await browser.newContext({
    userAgent: 'Mozilla/5.0 (compatible; U9itus-civic-bot/1.0; +https://u9itus.dev/about)',
    locale: 'en-US',
  });
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
  const browser = await chromium.launch({ headless: true });

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
