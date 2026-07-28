/**
 * Politicians Directory Duplicate Checker
 *
 * Crawls the public /politicians browse page (sorted by name, 24/page) end
 * to end, screenshotting every page and extracting each card's
 * name + office + location. Flags any (name, office, location) triple that
 * appears more than once — the signature of the persistDiscoveredOfficial()
 * race condition that let concurrent district-lookup requests create
 * multiple politicians rows for the same real person (see fed5fb39/b6a29f1c).
 * collapseDirectoryDuplicates() in PublicProfileController is supposed to
 * merge those before render, so any duplicate found here is a live
 * regression, not expected noise.
 *
 * Usage:
 *   node scripts/check-directory-duplicates.js --base-url=https://u9itus.dev
 *   node scripts/check-directory-duplicates.js --base-url=https://u9itus.dev --state=CA
 *   node scripts/check-directory-duplicates.js --base-url=http://localhost --max-pages=5 --out-dir=/tmp/shots
 *
 * Exit code: 1 if any duplicate is found (or the crawl errors), else 0.
 * Always writes {out-dir}/summary.json with the full duplicate report.
 */

import { chromium } from 'playwright';
import { writeFileSync, mkdirSync } from 'fs';
import { resolve } from 'path';

const args = Object.fromEntries(
  process.argv.slice(2)
    .filter(a => a.startsWith('--'))
    .map(a => {
      const [k, ...rest] = a.slice(2).split('=');
      return [k, rest.join('=') || 'true'];
    })
);

const BASE_URL  = (args['base-url'] ?? process.env.APP_URL ?? 'http://localhost').replace(/\/+$/, '');
const STATE     = args.state ? String(args.state).toUpperCase() : null;
const MAX_PAGES = Number(args['max-pages'] ?? 25);
const OUT_DIR   = resolve(process.cwd(), args['out-dir'] ?? 'storage/app/qa/politicians-directory');
const TIMEOUT   = 30_000;

mkdirSync(OUT_DIR, { recursive: true });

/** Collapse whitespace/case so trivial formatting differences don't hide (or fake) a match. */
function normalize(text) {
  return (text ?? '').replace(/\s+/g, ' ').trim().toLowerCase();
}

async function extractCards(page) {
  return page.$$eval('div.group.flex.flex-col.bg-slate-800\\/50', (cards) => {
    return cards.map((card) => {
      const name = card.querySelector('h3')?.textContent ?? '';
      const office = card.querySelector('p.text-slate-400.text-xs.mb-2.truncate')?.textContent ?? '';

      // Location row is identified by its pin icon path (see politicians-directory.blade.php),
      // not by class, since the governance-level/district/party rows share the same classes.
      const rows = Array.from(card.querySelectorAll('.mt-auto > div'));
      const locationRow = rows.find((row) => row.innerHTML.includes('M17.657 16.657L13.414 20.9'));
      const location = locationRow?.querySelector('span')?.textContent ?? '';

      const href = card.querySelector('a[href]')?.getAttribute('href') ?? null;

      return { name, office, location, href };
    });
  });
}

async function crawl() {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  page.setDefaultTimeout(TIMEOUT);

  /** @type {Map<string, {name:string, office:string, location:string, hrefs:Set<string>, pages:Set<number>}>} */
  const seen = new Map();
  const pagesCrawled = [];
  let totalCards = 0;

  try {
    for (let pageNum = 1; pageNum <= MAX_PAGES; pageNum++) {
      const query = new URLSearchParams({ sort: 'name', page: String(pageNum) });
      if (STATE) query.set('state', STATE);
      const url = `${BASE_URL}/politicians?${query.toString()}`;

      console.error(`[page ${pageNum}] ${url}`);
      await page.goto(url, { waitUntil: 'networkidle' });

      const cards = await extractCards(page);
      if (cards.length === 0) {
        console.error(`[page ${pageNum}] no cards — end of directory`);
        break;
      }

      const shotName = STATE ? `${STATE.toLowerCase()}-page-${String(pageNum).padStart(3, '0')}.png`
                              : `page-${String(pageNum).padStart(3, '0')}.png`;
      await page.screenshot({ path: resolve(OUT_DIR, shotName), fullPage: true });

      for (const card of cards) {
        const key = `${normalize(card.name)}|${normalize(card.office)}|${normalize(card.location)}`;
        if (!key.replace(/\|/g, '').trim()) continue; // skip cards we couldn't read any fields from

        if (!seen.has(key)) {
          seen.set(key, {
            name: card.name.trim(),
            office: card.office.trim(),
            location: card.location.trim(),
            hrefs: new Set(),
            pages: new Set(),
          });
        }
        const entry = seen.get(key);
        if (card.href) entry.hrefs.add(card.href);
        entry.pages.add(pageNum);
      }

      totalCards += cards.length;
      pagesCrawled.push(pageNum);

      if (cards.length < 24) {
        console.error(`[page ${pageNum}] short page (${cards.length} cards) — end of directory`);
        break;
      }
    }
  } finally {
    await browser.close();
  }

  const duplicates = [...seen.values()]
    .filter((entry) => entry.hrefs.size > 1) // distinct profile URLs for the same name+office+location = distinct DB rows
    .map((entry) => ({
      name: entry.name,
      office: entry.office,
      location: entry.location,
      profile_urls: [...entry.hrefs],
      seen_on_pages: [...entry.pages],
    }));

  return { pagesCrawled, totalCards, uniqueEntries: seen.size, duplicates };
}

const result = await crawl().catch((err) => {
  console.error('Crawl failed:', err.message);
  const summary = { base_url: BASE_URL, state: STATE, error: err.message, duplicates: [] };
  writeFileSync(resolve(OUT_DIR, 'summary.json'), JSON.stringify(summary, null, 2));
  process.exit(1);
});

const summary = {
  base_url: BASE_URL,
  state: STATE,
  crawled_at: new Date().toISOString(),
  pages_crawled: result.pagesCrawled.length,
  total_cards_seen: result.totalCards,
  unique_name_office_location_entries: result.uniqueEntries,
  duplicate_count: result.duplicates.length,
  duplicates: result.duplicates,
};
writeFileSync(resolve(OUT_DIR, 'summary.json'), JSON.stringify(summary, null, 2));

if (result.duplicates.length > 0) {
  console.error(`\nFound ${result.duplicates.length} duplicate politician(s):`);
  for (const dup of result.duplicates) {
    console.error(`  - ${dup.name} (${dup.office}, ${dup.location}) -> ${dup.profile_urls.join(', ')}`);
  }
  process.exit(1);
} else {
  console.error(`\nNo duplicates found across ${result.pagesCrawled.length} page(s) / ${result.totalCards} card(s).`);
  process.exit(0);
}
