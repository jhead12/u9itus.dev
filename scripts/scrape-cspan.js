/**
 * C-SPAN Video Scraper
 *
 * c-span.org has no public video API and renders its search results
 * client-side (the /search page returns a "Not yet implemented" shell that a
 * JS bundle populates). This script drives real Chromium via Playwright,
 * waits for the video results to render, and emits normalized clip JSON.
 *
 * Mirrors scripts/scrape-opensecrets.js (ESM, Playwright, realistic browser
 * context, JSON to stdout / --out, stderr diagnostics, DELAY/TIMEOUT).
 *
 * Usage:
 *   node scripts/scrape-cspan.js --name="Alex Padilla"
 *   node scripts/scrape-cspan.js --name="Alex Padilla California" --max=10
 *   node scripts/scrape-cspan.js --name="Alex Padilla" --out=storage/app/cspan.json
 *
 * Output (stdout JSON):
 *   {
 *     "clips": [
 *       { "source_id": "519764", "title": "...", "url": "https://www.c-span.org/video/?519764",
 *         "thumbnail_url": "...", "published_at": "2024-07-23T00:00:00", "duration_seconds": 3600 }
 *     ]
 *   }
 *   On error: stderr diagnostics + process exit 1 (no stdout JSON).
 *
 * Requirements:
 *   npm install playwright
 *   npx playwright install chromium
 *
 * NOTE: c-span.org markup can change. Selectors here are deliberately broad
 * (any /video/?<id> or /event/.../<id> anchor) so the script keeps working
 * across layout tweaks; per-card metadata (thumbnail/date/duration) is
 * best-effort and gracefully nulls out. Tune after the first real run if a
 * specific field stops populating — the stderr log shows result counts.
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

const NAME_ARG = args.name ?? null;          // search query (name + office + state)
const MAX_ARG  = Number(args.max ?? 10);     // cap clips returned
const OUT_PATH = args.out ? resolve(process.cwd(), args.out) : null;

const DELAY_MS = 800;
const TIMEOUT  = 25_000;
const BASE     = 'https://www.c-span.org';

// A realistic desktop Chrome UA (C-SPAN blocks the default Playwright UA /
// plain curl with a 403). Present as a normal visit so results render.
const REAL_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36';

const sleep = (ms) => new Promise(r => setTimeout(r, ms));

async function newScrapeContext(browser) {
  const ctx = await browser.newContext({
    userAgent: REAL_UA,
    locale: 'en-US',
    extraHTTPHeaders: { 'Accept-Language': 'en-US,en;q=0.9' },
  });
  await ctx.addInitScript(() => {
    Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
  });
  return ctx;
}

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Extract a C-SPAN video id + slug from an href. Handles:
 *   /video/?519764-1/slug            → id "519764-1"
 *   /video/standalone/?519764         → id "519764"
 *   /event/campaign-2026/slug/445416 → id "445416"
 */
function parseVideoId(href) {
  let m = href.match(/\/video\/\?([\w-]+)/);
  if (m) return m[1];
  m = href.match(/\/event\/[^?#]+\/([\w-]+)(?:[?#]|$)/);
  if (m) return m[1];
  return null;
}

/** "1:23:45" / "23:45" / "90" → seconds; else null. */
function parseDuration(text) {
  if (!text) return null;
  const parts = text.trim().split(':').map(Number);
  if (parts.some(isNaN)) return null;
  if (parts.length === 3) return parts[0] * 3600 + parts[1] * 60 + parts[2];
  if (parts.length === 2) return parts[0] * 60 + parts[1];
  if (parts.length === 1 && parts[0] > 0) return parts[0];
  return null;
}

/** Parse a C-SPAN air-date string ("July 23, 2024", "2024-07-23", "7/23/2024") → ISO, else null. */
function parseDate(text) {
  if (!text) return null;
  const s = text.trim();
  // ISO first
  let m = s.match(/(\d{4})-(\d{2})-(\d{2})/);
  if (m) return `${m[1]}-${m[2]}-${m[3]}T00:00:00`;
  // "Month D, YYYY"
  const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  m = s.match(/([A-Za-z]+)\s+(\d{1,2}),?\s+(\d{4})/);
  if (m) {
    const mi = months.findIndex(x => x.toLowerCase() === m[1].toLowerCase());
    if (mi >= 0) return `${m[3]}-${String(mi + 1).padStart(2, '0')}-${m[2].padStart(2, '0')}T00:00:00`;
  }
  // M/D/YYYY
  m = s.match(/(\d{1,2})\/(\d{1,2})\/(\d{4})/);
  if (m) return `${m[3]}-${m[1].padStart(2, '0')}-${m[2].padStart(2, '0')}T00:00:00`;
  return null;
}

// ── Scrape ───────────────────────────────────────────────────────────────────

/**
 * Search C-SPAN for a query and return normalized clip candidates.
 */
async function scrapeClips(browser, query, max) {
  const ctx = await newScrapeContext(browser);
  const page = await ctx.newPage();

  const searchUrl = `${BASE}/search/?searchtype=Video&query=${encodeURIComponent(query)}`;
  console.error(`  [search] "${query}" → ${searchUrl}`);

  try {
    await page.goto(searchUrl, { waitUntil: 'domcontentloaded', timeout: TIMEOUT });

    // Results render client-side. Give the JS bundle room, then wait for any
    // video anchor to appear. Fall back to networkidle + a delay if none show
    // quickly (so a slow render still gets caught before we give up).
    try {
      await page.waitForSelector('a[href*="/video/?"], a[href*="/event/"]', { timeout: TIMEOUT });
    } catch {
      await page.waitForLoadState('networkidle', { timeout: TIMEOUT }).catch(() => {});
      await sleep(DELAY_MS * 2);
    }
    // Extra settle for lazy thumbnails/dates.
    await sleep(DELAY_MS);

    const clips = await page.$$eval('a[href*="/video/?"], a[href*="/event/"]', (anchors) => {
      const seen = new Set();
      const out = [];
      for (const a of anchors) {
        const href = a.getAttribute('href') || '';
        // Match the watch-page forms only (standalone embeds, series, etc. excluded).
        const idMatch = href.match(/\/video\/\?([\w-]+)/) || href.match(/\/event\/[^?#]+\/([\w-]+)(?:[?#]|$)/);
        if (!idMatch) continue;
        const id = idMatch[1];
        if (seen.has(id)) continue;
        seen.add(id);

        // Walk up to the nearest card-like container for metadata.
        const card = a.closest('li, article, [class*="result"], [class*="video"], [class*="card"]') || a.parentElement;
        const title = (a.textContent || '').trim();
        const img = card ? card.querySelector('img') : null;
        const imgSrc = img ? (img.getAttribute('src') || img.getAttribute('data-src') || '') : '';

        // Best-effort date + duration from the card's text.
        const cardText = card ? (card.textContent || '') : '';
        const dateMatch = cardText.match(/([A-Za-z]+\s+\d{1,2},?\s+\d{4}|\d{1,2}\/\d{1,2}\/\d{4}|\d{4}-\d{2}-\d{2})/);
        const durMatch = cardText.match(/\b(\d{1,2}:\d{2}(?::\d{2})?)\b/);

        out.push({
          source_id: id,
          title,
          url: a.href, // absolute href the browser resolves
          thumbnail_url: imgSrc || null,
          published_at: dateMatch ? dateMatch[1] : null,
          duration_seconds: durMatch ? durMatch[1] : null, // raw "h:mm:ss"; converted below
        });
      }
      return out;
    });

    // Resolve relative thumbnail URLs + parse duration to seconds (JS-side).
    const resolved = clips.map((c) => ({
      ...c,
      thumbnail_url: c.thumbnail_url
        ? (c.thumbnail_url.startsWith('http')
          ? c.thumbnail_url
          : new URL(c.thumbnail_url, BASE).href)
        : null,
      duration_seconds: typeof c.duration_seconds === 'string'
        ? parseDuration(c.duration_seconds)
        : c.duration_seconds,
      published_at: c.published_at ? parseDate(c.published_at) : null,
    }));

    console.error(`  [search] results=${resolved.length}`);
    return resolved.slice(0, max);
  } catch (err) {
    console.error(`  [search] Error: ${err.message}`);
    return null;
  } finally {
    await ctx.close();
  }
}

// ── Main ─────────────────────────────────────────────────────────────────────

async function main() {
  if (!NAME_ARG) {
    console.error('Usage: node scrape-cspan.js --name="Alex Padilla" [--max=10] [--out=path]');
    process.exit(1);
  }

  const browser = await chromium.launch({
    headless: true,
    args: ['--disable-blink-features=AutomationControlled'],
  });

  try {
    const clips = await scrapeClips(browser, NAME_ARG, MAX_ARG);
    if (clips === null) {
      // Error already logged to stderr; exit non-zero so the service records 'failed'.
      process.exit(1);
    }
    const output = JSON.stringify({ clips }, null, 2);
    if (OUT_PATH) {
      mkdirSync(dirname(OUT_PATH), { recursive: true });
      writeFileSync(OUT_PATH, output, 'utf8');
      console.error(`\n✓ Written to ${OUT_PATH}`);
    } else {
      console.log(output);
    }
  } finally {
    await browser.close();
  }
}

main().catch((err) => {
  console.error(`  Fatal: ${err.message}`);
  process.exit(1);
});