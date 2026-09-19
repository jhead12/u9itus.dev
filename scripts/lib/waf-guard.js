/**
 * WAF / bot-challenge guard for the Playwright scrapers.
 *
 * Ballotpedia (AWS WAF), OpenSecrets (Cloudflare) and friends answer a
 * scraper they don't like with a challenge page instead of an error: HTTP 202
 * with an empty body, a 200 "Just a moment..." interstitial, or a 403/429. A
 * scraper that keeps walking a 400-URL list through that wastes the job's whole
 * time budget for ~0% yield, and leaves nothing behind to show what the WAF
 * actually served.
 *
 * WafGuard gives each scraper two things:
 *   1. Evidence — a screenshot + response details of what was served, saved
 *      under storage/app/waf-screenshots/ (uploaded as a workflow artifact) and
 *      summarised on the job's summary page.
 *   2. A circuit breaker — after `maxConsecutive` blocked pages in a row
 *      (default 10) `tripped` turns true so the caller can skip the rest of the
 *      list. Any successful page resets the count.
 *
 *   const guard = new WafGuard({ label: 'ballotpedia' });
 *   ...
 *   if (blocked) await guard.block(page, url, reason);   // may trip
 *   else guard.ok();
 *   if (guard.tripped) break;
 *   ...
 *   guard.finish();   // writes blocks.json + job summary
 */
import { mkdirSync, writeFileSync, appendFileSync, readdirSync } from 'fs';
import { resolve } from 'path';

export const DEFAULT_MAX_CONSECUTIVE = 10;
export const DEFAULT_OUT_DIR = 'storage/app/waf-screenshots';

/** Statuses a WAF uses for a challenge/deny. 202 is AWS WAF's empty-body JS challenge. */
const CHALLENGE_STATUSES = new Set([202, 403, 429]);

const CHALLENGE_TITLE = /just a moment|attention required|security verification|are you a human|verify(ing)? (that )?you are (a )?human|access denied|request blocked|robot or human/i;

/**
 * Why a response looks like a WAF challenge, or null when it looks like a real page.
 *
 * @param {import('playwright').Response|null} response  page.goto() result
 * @param {string} [title]  document title (challenge interstitials often return 200)
 * @returns {string|null}
 */
export function detectChallenge(response, title = '') {
  const status = response?.status?.();
  const wafAction = response?.headers?.()['x-amzn-waf-action'];

  if (wafAction) return `AWS WAF ${wafAction} (HTTP ${status})`;
  if (status && CHALLENGE_STATUSES.has(status)) return `HTTP ${status}`;
  if (CHALLENGE_TITLE.test(title ?? '')) return `bot-check page "${title}"`;
  return null;
}

function slugify(text) {
  return String(text).replace(/^https?:\/\//, '').replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '').slice(0, 60);
}

export class WafGuard {
  /**
   * @param {object}  opts
   * @param {string}  opts.label            short site name used in filenames ("ballotpedia")
   * @param {number} [opts.maxConsecutive]  blocked pages in a row before tripping; env WAF_MAX_CONSECUTIVE overrides the default
   * @param {string} [opts.outDir]          where screenshots + blocks.json go
   * @param {number} [opts.maxScreenshots]  cap per label per outDir, so a long run can't fill the artifact
   * @param {boolean}[opts.quiet]           one-page-per-process scrapers: keep screenshots but leave the
   *                                        manifest / job summary / annotation to the parent that spawns them
   */
  constructor({ label, maxConsecutive, outDir = DEFAULT_OUT_DIR, maxScreenshots = 10, quiet = false }) {
    const fromEnv = Number.parseInt(process.env.WAF_MAX_CONSECUTIVE ?? '', 10);
    this.label = label;
    this.maxConsecutive = maxConsecutive ?? (fromEnv > 0 ? fromEnv : DEFAULT_MAX_CONSECUTIVE);
    this.outDir = resolve(process.cwd(), outDir);
    this.maxScreenshots = maxScreenshots;
    this.quiet = quiet;
    this.consecutive = 0;
    this.blocks = [];
    this.skipped = 0;
  }

  get tripped() {
    return this.consecutive >= this.maxConsecutive;
  }

  /** A page loaded for real — the WAF is letting us through again. */
  ok() {
    this.consecutive = 0;
  }

  /** Record how many queued pages were skipped because the guard tripped. */
  noteSkipped(count) {
    this.skipped += count;
  }

  /**
   * Record a blocked page and screenshot what the WAF served.
   * Never throws — evidence gathering must not turn a block into a crash.
   *
   * @param {import('playwright').Page|null} page  still-open page showing the challenge
   * @param {string} url
   * @param {string} reason  from detectChallenge() or the error message
   */
  async block(page, url, reason) {
    this.consecutive++;

    const entry = { url, reason, at: new Date().toISOString(), screenshot: null, body: null };

    if (page) {
      entry.body = await page
        .evaluate(() => (document.body?.innerText ?? '').replace(/\s+/g, ' ').trim().slice(0, 300))
        .catch(() => null);
      entry.screenshot = await this.#screenshot(page, url);
    }

    this.blocks.push(entry);
    console.warn(`  🛡 WAF block #${this.consecutive} (${reason}) — ${url}${entry.screenshot ? ` [screenshot: ${entry.screenshot}]` : ''}`);
    if (this.tripped) {
      console.warn(`  ⛔ ${this.consecutive} consecutive WAF blocks on ${this.label} — skipping the remaining pages.`);
    }
    return this.tripped;
  }

  async #screenshot(page, url) {
    try {
      mkdirSync(this.outDir, { recursive: true });
      const taken = readdirSync(this.outDir).filter(f => f.startsWith(`${this.label}-`) && f.endsWith('.png')).length;
      if (taken >= this.maxScreenshots) return null;

      const file = `${this.label}-${new Date().toISOString().replace(/[:.]/g, '-')}-${slugify(url)}.png`;
      await page.screenshot({ path: resolve(this.outDir, file), timeout: 10_000 });
      return file;
    } catch {
      return null; // page already closed / navigating — the manifest entry still records the block
    }
  }

  /** Write the manifest and job summary. No-op when nothing was blocked. */
  finish() {
    if (this.quiet || this.blocks.length === 0) return;

    mkdirSync(this.outDir, { recursive: true });
    const manifest = {
      label: this.label,
      blocked_pages: this.blocks.length,
      tripped: this.tripped,
      skipped_pages: this.skipped,
      blocks: this.blocks,
    };
    writeFileSync(resolve(this.outDir, `${this.label}-blocks.json`), JSON.stringify(manifest, null, 2));

    const headline = this.tripped
      ? `${this.label}: stopped after ${this.consecutive} consecutive WAF blocks; ${this.skipped} page(s) skipped — data for this run is partial`
      : `${this.label}: ${this.blocks.length} page(s) hit a WAF challenge (run recovered)`;
    // GitHub Actions turns this into a warning annotation on the run.
    console.log(`::warning title=WAF challenge::${headline}`);

    if (process.env.GITHUB_STEP_SUMMARY) {
      const rows = this.blocks.slice(0, 20).map(b => `| ${b.url} | ${b.reason} | ${b.screenshot ?? '—'} |`);
      appendFileSync(
        process.env.GITHUB_STEP_SUMMARY,
        `\n### 🛡 WAF challenges — ${this.label}\n\n${headline}\n\n| Page | Response | Screenshot |\n|---|---|---|\n${rows.join('\n')}\n`,
      );
    }
  }
}
