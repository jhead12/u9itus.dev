import { test, expect, type Page } from '@playwright/test';

/**
 * Map UX refresh: visible search, explicit color mode + legend, one lightweight
 * first-visit hint instead of an auto-launched tour, flat-by-default view with
 * optional 3D, readable state abbreviations, and a state panel that leads with
 * districts and representatives.
 *
 * The state outlines come from a CDN TopoJSON, so these tests need network
 * access. District rows are built from static district counts, so they don't
 * depend on the Census boundary service.
 */

const HINT_KEY = 'u9_map_first_hint_v1';
const TOUR_KEY = 'u9_map_tour_v1';
const VIEW_KEY = 'u9_map_view_mode';

async function openMap(page: Page) {
    await page.goto('/map');
    await page.waitForSelector('#btn-search', { state: 'visible', timeout: 20000 });
    // The cookie notice owns the bottom edge; clear it so it can't cover controls.
    const gotIt = page.getByRole('button', { name: 'Got it' });
    if (await gotIt.isVisible().catch(() => false)) await gotIt.click();
    await page.waitForFunction(() => document.querySelectorAll('.state-label').length > 0, undefined, { timeout: 20000 });
}

async function selectState(page: Page, name: string) {
    await page.click('#btn-search');
    await page.fill('#search-input', name);
    await page.keyboard.press('Enter');
    await expect(page.locator('#panel-state')).toHaveText(name, { timeout: 15000 });
}

test.describe('map (desktop)', () => {
    test.use({ viewport: { width: 1440, height: 900 } });

    test('search is a visible labeled field and Find my district is a labeled button', async ({ page }) => {
        await openMap(page);

        const search = page.locator('#btn-search');
        await expect(search).toBeVisible();
        await expect(search).toContainText('Search state, district, or candidate');

        const find = page.getByRole('button', { name: 'Find my district using my location' });
        await expect(find).toBeVisible();
        await expect(find).toContainText('Find my district');

        await search.click();
        await expect(page.locator('#search-overlay')).toHaveClass(/open/);
        await expect(page.locator('#search-input')).toBeFocused();
    });

    test('the current color mode is stated, switchable, and explained by the legend', async ({ page }) => {
        await openMap(page);

        const legend = page.locator('#legend');
        const regions = legend.getByRole('button', { name: 'Regions', exact: true });
        const party = legend.getByRole('button', { name: 'Party control', exact: true });

        await expect(regions).toHaveAttribute('aria-pressed', 'true');
        await expect(party).toHaveAttribute('aria-pressed', 'false');
        await expect(page.locator('#legend-title')).toHaveText('Regions');
        await expect(page.locator('#legend-note')).toContainText('not party');
        await expect(legend.locator('.legend-row')).toHaveCount(5);

        await party.click();
        await expect(party).toHaveAttribute('aria-pressed', 'true');
        await expect(regions).toHaveAttribute('aria-pressed', 'false');
        await expect(page.locator('#legend-title')).toHaveText('Party control');
        await expect(page.locator('#legend-note')).toContainText("governor's party");
        await expect(legend).toContainText('Democratic');
        await expect(legend).toContainText('Republican');

        await regions.click();
        await expect(page.locator('#legend-title')).toHaveText('Regions');
    });

    test('the color mode switch stays reachable in region view and applies there', async ({ page }) => {
        await openMap(page);
        await page.locator('#legend .legend-row', { hasText: 'Northeast' }).click();
        await expect(page.locator('#panel-state')).toContainText('Northeast Region');

        // The open info panel must not cover the legend.
        const party = page.locator('#legend').getByRole('button', { name: 'Party control', exact: true });
        await party.click();
        await expect(page.locator('#legend-title')).toHaveText('Party control');
        await expect(party).toHaveAttribute('aria-pressed', 'true');

        await page.locator('#breadcrumb .bc-link', { hasText: 'Overview' }).click();
        await expect(page.locator('#info-panel')).not.toHaveClass(/open/);
        await expect(page.locator('#legend-title')).toHaveText('Party control');
    });

    test('region colors avoid the party colors red and blue', async ({ page }) => {
        await openMap(page);
        const swatches = await page.locator('#legend .legend-swatch').evaluateAll((els) =>
            els.map((el) => getComputedStyle(el).backgroundColor),
        );
        const toRgb = (c: string) => c.match(/\d+/g)!.map(Number);
        for (const c of swatches) {
            const [r, g, b] = toRgb(c);
            const isRed = r > 200 && g < 90 && b < 90;
            const isBlue = b > 200 && r < 90 && g > 90 && g < 160;
            expect(isRed || isBlue, `region swatch ${c} reads as a party color`).toBe(false);
        }
    });

    test('a first visit gets one lightweight hint, not the tour, and it stays dismissed', async ({ page }) => {
        await openMap(page);

        await expect(page.locator('#tutorial-overlay')).not.toHaveClass(/active/);
        const hint = page.locator('#map-first-hint');
        await expect(hint).toHaveClass(/visible/, { timeout: 5000 });
        await expect(hint).toContainText('Select a state to explore its districts.');

        await hint.getByRole('button', { name: 'Dismiss hint' }).click();
        await expect(hint).not.toHaveClass(/visible/);
        expect(await page.evaluate((k) => localStorage.getItem(k), HINT_KEY)).toBe('1');

        await page.reload();
        await page.waitForSelector('#btn-search', { state: 'visible' });
        await page.waitForTimeout(2500);
        await expect(page.locator('#map-first-hint')).not.toHaveClass(/visible/);
        await expect(page.locator('#tutorial-overlay')).not.toHaveClass(/active/);
    });

    test('selecting a state dismisses the hint', async ({ page }) => {
        await openMap(page);
        await expect(page.locator('#map-first-hint')).toHaveClass(/visible/, { timeout: 5000 });
        await selectState(page, 'Ohio');
        await expect(page.locator('#map-first-hint')).not.toHaveClass(/visible/);
    });

    test('the full tour is available under Help', async ({ page }) => {
        await page.addInitScript((k) => localStorage.setItem(k, '1'), TOUR_KEY);
        await openMap(page);

        await page.getByRole('button', { name: 'Help: how to use this map' }).click();
        await page.getByRole('button', { name: 'Take the full tour' }).click();

        await expect(page.locator('#tutorial-overlay')).toHaveClass(/active/);
        await expect(page.locator('#tutorial-card')).toContainText('Step 1 of 8');
    });

    test('state abbreviations are shown on the overview map', async ({ page }) => {
        await openMap(page);
        await page.waitForTimeout(500);
        const shown = await page.locator('.state-label').evaluateAll(
            (els) => els.filter((el) => (el as HTMLElement).style.display === 'flex').map((el) => el.textContent),
        );
        expect(shown.length).toBeGreaterThanOrEqual(35);
        for (const abbr of ['TX', 'CA', 'OH']) expect(shown).toContain(abbr);
    });

    test('flat is the default and 3D is an optional, remembered mode', async ({ page }) => {
        await openMap(page);
        const toggle = page.locator('#btn-3d');
        await expect(toggle).toHaveAttribute('aria-pressed', 'false');
        expect(await page.evaluate((k) => localStorage.getItem(k), VIEW_KEY)).toBeNull();

        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-pressed', 'true');
        expect(await page.evaluate((k) => localStorage.getItem(k), VIEW_KEY)).toBe('3d');

        await page.reload();
        await page.waitForSelector('#btn-3d', { state: 'visible' });
        await expect(page.locator('#btn-3d')).toHaveAttribute('aria-pressed', 'true');

        await page.keyboard.press('d');
        await expect(page.locator('#btn-3d')).toHaveAttribute('aria-pressed', 'false');
        expect(await page.evaluate((k) => localStorage.getItem(k), VIEW_KEY)).toBe('flat');
    });

    test('a selected state leads with its districts; other states are collapsed and last', async ({ page }) => {
        await openMap(page);
        await selectState(page, 'Ohio');

        const rows = page.locator('#panel-districts .dist-row');
        await expect(rows).toHaveCount(15);
        await expect(page.locator('#panel-districts .pd-title')).toContainText('Districts & representatives');
        // Long lists start trimmed so the rest of the panel stays in reach.
        await expect(rows.nth(7)).toBeVisible();
        await expect(rows.nth(8)).toBeHidden();
        await page.getByRole('button', { name: 'Show all 15 districts' }).click();
        await expect(rows.nth(14)).toBeVisible();

        // Vertical order inside the panel: districts first, other states last.
        // offsetTop is relative to the panel, so it ignores how far the panel is scrolled.
        // (#panel-running-candidates has no box of its own, so it isn't measured here.)
        const order = await page.evaluate(() => {
            const top = (sel: string) => (document.querySelector(sel) as HTMLElement).offsetTop;
            return {
                districts: top('#panel-districts'),
                offices: top('#offices-toggle'),
                siblings: top('#panel-states-wrap'),
            };
        });
        expect(order.districts).toBeLessThan(order.offices);

        const domOrder = await page.evaluate(() => {
            const kids = [...document.getElementById('info-panel')!.children].map((c) => c.id);
            return kids;
        });
        expect(domOrder.indexOf('panel-districts')).toBeLessThan(domOrder.indexOf('panel-candidates'));
        expect(domOrder.indexOf('panel-states-wrap')).toBe(domOrder.length - 1);

        const siblings = page.locator('#panel-states-wrap');
        await expect(page.locator('#panel-states-summary')).toHaveText(/Other Midwest states/i);
        expect(await siblings.evaluate((el: HTMLDetailsElement) => el.open)).toBe(false);
        expect(order.siblings).toBeGreaterThan(order.offices);
    });

    test('clicking a district row opens that district and marks the row active', async ({ page }) => {
        await openMap(page);
        await selectState(page, 'Ohio');

        await page.locator('#panel-districts .dist-row').nth(2).click();
        await expect(page.locator('#panel-state')).toContainText('District 3');
        await expect(page.locator('#panel-districts .dist-row.active')).toHaveCount(1);
        await expect(page.locator('#panel-districts .dist-row.active')).toContainText('OH-03');
        // The list stays put while the district's own details load below it.
        await expect(page.locator('#panel-districts .dist-row')).toHaveCount(15);
    });

    test('other states in the region can be switched to from the collapsed list', async ({ page }) => {
        await openMap(page);
        await selectState(page, 'Ohio');

        await page.locator('#panel-states-summary').click();
        await page.getByRole('button', { name: 'Indiana', exact: true }).click();
        await expect(page.locator('#panel-state')).toHaveText('Indiana', { timeout: 15000 });
        await expect(page.locator('#panel-districts .dist-row')).toHaveCount(9);
    });
});

test.describe('map (mobile)', () => {
    test.use({ viewport: { width: 390, height: 780 }, hasTouch: true, isMobile: true });

    test('search stays visible, nothing overflows, and the hint clears the legend', async ({ page }) => {
        await openMap(page);

        await expect(page.locator('#btn-search')).toBeVisible();
        await expect(page.locator('#btn-search')).toContainText('Search');
        expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(0);

        const hint = page.locator('#map-first-hint');
        await expect(hint).toHaveClass(/visible/, { timeout: 5000 });
        const [h, l] = await Promise.all([hint.boundingBox(), page.locator('#legend').boundingBox()]);
        expect(h!.y).toBeGreaterThan(l!.y + l!.height);
    });
});

/**
 * District selection: the panel leads with the selected seat, one election
 * date everywhere, useful content while boundaries load (with Retry on
 * failure), an unmistakable selection on the map, and a searchable list.
 */

const OHIO_PAYLOAD = {
    state: 'OH',
    region: 'Midwest',
    total: 0,
    offices: [
        { office: 'U.S. Senators', election_phase: 'pre_primary', candidates: [
            { full_name: 'Sherrod Test', party: 'Democratic', status: 'seated', is_running: false, verified: true, source: 'platform' },
        ] },
    ],
    house_candidates: {
        'OH-03': [
            { full_name: 'Joyce Beatty', party: 'Democratic', status: 'seated', is_running: false, verified: true, source: 'platform' },
            { full_name: 'Casey Challenger', party: 'Republican', status: 'running', is_running: true, verified: false, source: 'scraped', primary_result: 'advanced_to_general', general_date: '2026-11-03', source_label: 'News discovery (unverified)', updated_at: new Date(Date.now() - 3 * 86400000).toISOString() },
        ],
        'OH-12': [
            { full_name: 'Troy Balderson', party: 'Republican', status: 'seated', is_running: false, verified: true, source: 'platform' },
        ],
    },
    city_officials: {},
    ballot_measures: [],
    election_dates: [
        { stage_name: 'General', election_date: '2026-11-03', election_date_formatted: 'Nov 3, 2026', filing_deadline: null, filing_deadline_formatted: null },
    ],
    general_election_date: '2026-11-03',
    quality: { hidden_names: 0, merged_duplicates: 0, date_conflicts: 0 },
    office_roles: {},
    population: null,
    district_populations: [],
};

async function stubOhio(page: Page) {
    await page.route('**/api/v1/map/state-candidates*', (route) =>
        route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(OHIO_PAYLOAD) }));
}

test.describe('map district panel (desktop)', () => {
    test.use({ viewport: { width: 1440, height: 900 }, timezoneId: 'America/Los_Angeles' });

    test.beforeEach(async ({ page }) => {
        // Cached payloads would skip the stub.
        await page.addInitScript(() => localStorage.removeItem('u9_map_sc_OH'));
        await stubOhio(page);
    });

    test('leads with the representative, then this seat, then statewide, then other races', async ({ page }) => {
        await openMap(page);
        await selectState(page, 'Ohio');
        await page.locator('#panel-districts .dist-row').nth(2).click();
        await expect(page.locator('#panel-state')).toContainText('District 3');

        const headings = page.locator('#panel-candidates .dp-title');
        await expect(headings).toHaveText(['Your representative', 'Candidates for this seat', 'Statewide races · Ohio']);
        await expect(page.locator('#panel-candidates .dp-rep')).toContainText('Joyce Beatty');
        await expect(page.locator('#panel-candidates')).toContainText('Casey Challenger');

        // Screen order: header → this seat's content → switcher → other races.
        const tops = await page.evaluate(() => {
            const top = (sel: string) => document.querySelector(sel)!.getBoundingClientRect().top;
            return {
                rep: top('#panel-candidates .dp-rep'),
                seat: top('#panel-candidates .dp-section:nth-of-type(2)'),
                switcher: top('#panel-districts'),
                other: top('#panel-running-candidates'),
            };
        });
        expect(tops.rep).toBeLessThan(tops.seat);
        expect(tops.seat).toBeLessThan(tops.switcher);
        expect(tops.switcher).toBeLessThan(tops.other);
        // The representative is in the first screenful, not pushed down by a state-wide list.
        expect(tops.rep).toBeLessThan(400);

        // "Other races" is collapsed and titled for what it is.
        await expect(page.locator('#rc-section')).toHaveClass(/collapsed/);
        await expect(page.locator('#rc-section .office-title')).toContainText('Other races in Ohio');
        // The district switcher is folded away but reachable.
        await expect(page.locator('#panel-districts summary')).toHaveText('Switch district');
    });

    test('shows one election date, the state calendar date, even in a US timezone', async ({ page }) => {
        await openMap(page);
        await selectState(page, 'Ohio');
        await page.locator('#panel-districts .dist-row').nth(2).click();
        await expect(page.locator('#panel-candidates')).toContainText('Nov 3, 2026');

        const text = await page.locator('#panel-candidates').innerText();
        expect(text).not.toMatch(/Nov(ember)? 2\b/);
        expect(text).toMatch(/general election Nov 3, 2026|General election · Nov 3, 2026/i);
    });

    test('a candidate card date matches the calendar date too', async ({ page }) => {
        await openMap(page);
        await selectState(page, 'Ohio');
        await page.locator('#panel-districts .dist-row').nth(2).click();
        const card = page.locator('#panel-candidates .candidate-card', { hasText: 'Casey Challenger' });
        await expect(card).toContainText('Nov 3, 2026');
    });

    test('the panel is usable while boundaries load, and says so', async ({ page }) => {
        let release!: () => void;
        const gate = new Promise<void>((r) => { release = r; });
        await page.route('**/tigerweb.geo.census.gov/**', async (route) => { await gate; await route.continue(); });

        await openMap(page);
        await selectState(page, 'Ohio');

        await expect(page.locator('#pd-status')).toContainText('Loading district boundaries');
        // Everything else is already there.
        await expect(page.locator('#panel-districts .dist-row')).toHaveCount(15);
        await expect(page.locator('#panel-districts .dist-row').nth(2)).toContainText('Joyce Beatty');

        await page.locator('#panel-districts .dist-row').nth(2).click();
        await expect(page.locator('#panel-candidates .dp-rep')).toContainText('Joyce Beatty');
        await expect(page.locator('#selected-district-label')).toBeHidden();

        release();
        await expect(page.locator('#pd-status')).toBeHidden({ timeout: 30000 });
        // The selection made while loading is drawn once the shapes exist.
        await expect(page.locator('#selected-district-label')).toBeVisible({ timeout: 10000 });
        await expect(page.locator('#selected-district-label')).toContainText('OH-03');
    });

    test('a failed boundary load offers Retry and recovers', async ({ page }) => {
        let fail = true;
        await page.route('**/tigerweb.geo.census.gov/**', (route) => (fail ? route.abort('failed') : route.continue()));

        await openMap(page);
        await selectState(page, 'Ohio');

        const status = page.locator('#pd-status');
        await expect(status).toContainText('Couldn’t load the district boundaries', { timeout: 20000 });
        // The list and the panel still work without shapes.
        await page.locator('#pd-search').fill('oh-12');
        await page.locator('#panel-districts .dist-row:not([hidden])').click();
        await expect(page.locator('#panel-candidates .dp-rep')).toContainText('Troy Balderson');

        fail = false;
        await status.getByRole('button', { name: 'Retry' }).click();
        await expect(status).toBeHidden({ timeout: 30000 });
        await expect(page.locator('#selected-district-label')).toBeVisible({ timeout: 10000 });
        await expect(page.locator('#selected-district-label')).toContainText('OH-12');
    });

    test('the selected district gets a persistent label that follows the camera', async ({ page }) => {
        await openMap(page);
        await selectState(page, 'Ohio');
        await expect(page.locator('#pd-status')).toBeHidden({ timeout: 30000 });

        await page.locator('#panel-districts .dist-row').nth(2).click();
        const label = page.locator('#selected-district-label');
        await expect(label).toBeVisible();
        await expect(label).toContainText('OH-03');
        await expect(label).toContainText('Joyce Beatty');
        // Not covered by, or a duplicate of, the regular district label.
        await expect(page.locator('.map-label', { hasText: 'OH-03' })).toBeHidden();

        const before = await label.boundingBox();
        await page.mouse.move(900, 500);
        await page.mouse.wheel(0, -300);
        await page.waitForTimeout(600);
        await expect(label).toBeVisible();
        const after = await label.boundingBox();
        expect(after).not.toBeNull();
        expect(Math.abs(after!.x - before!.x) + Math.abs(after!.y - before!.y)).toBeGreaterThan(1);

        // Switching district moves the label.
        await page.locator('#panel-districts summary').click();
        await page.locator('#pd-search').fill('oh-12');
        await page.locator('#panel-districts .dist-row:not([hidden])').click();
        await expect(label).toContainText('OH-12');
        // The active row is never left hidden behind the "Show all" trim.
        await page.locator('#panel-districts summary').click();
        await page.locator('#pd-search').fill('');
        await expect(page.locator('#panel-districts .dist-row.active')).toBeVisible();
    });
});

test.describe('map district list search (desktop)', () => {
    test.use({ viewport: { width: 1440, height: 900 } });

    test.beforeEach(async ({ page }) => {
        await page.addInitScript(() => localStorage.removeItem('u9_map_sc_OH'));
        await stubOhio(page);
    });

    test('filters by number, code, representative and party, and reports empty results', async ({ page }) => {
        await openMap(page);
        await selectState(page, 'Ohio');

        const search = page.locator('#pd-search');
        const visible = page.locator('#pd-list .dist-row:not([hidden])');
        await expect(search).toBeVisible();
        await expect(search).toHaveAttribute('placeholder', /district, representative, or party/i);

        // A bare number means that district, not every district containing the digit.
        await search.fill('3');
        await expect(visible).toHaveCount(1);
        await expect(visible.first()).toContainText('OH-03');
        await expect(page.locator('#pd-count')).toHaveText('1 of 15 districts');

        await search.fill('oh-12');
        await expect(visible).toHaveCount(1);
        await expect(visible.first()).toContainText('Troy Balderson');

        await search.fill('beatty');
        await expect(visible).toHaveCount(1);
        await expect(visible.first()).toContainText('OH-03');

        // Matches beyond the first eight are shown, not trimmed by "Show all".
        await search.fill('republican');
        await expect(visible.first()).toBeVisible();

        await search.fill('zzz');
        await expect(visible).toHaveCount(0);
        await expect(page.locator('#pd-count')).toContainText('No district matches');

        await search.fill('');
        await expect(page.locator('#pd-count')).toBeHidden();
        await expect(page.locator('#pd-list .dist-row:not([hidden])')).toHaveCount(15);
    });

    test('is keyboard-operable: Enter picks the first match, arrows move through rows', async ({ page }) => {
        await openMap(page);
        await selectState(page, 'Ohio');

        await page.locator('#pd-search').fill('balderson');
        await page.keyboard.press('Enter');
        await expect(page.locator('#panel-state')).toContainText('District 12');

        await page.locator('#panel-districts summary').click();
        await page.locator('#pd-search').fill('');
        await page.locator('#pd-search').focus();
        await page.keyboard.press('ArrowDown');
        await expect(page.locator('#pd-list .dist-row').first()).toBeFocused();
        await page.keyboard.press('ArrowDown');
        await expect(page.locator('#pd-list .dist-row').nth(1)).toBeFocused();
        await page.keyboard.press('ArrowUp');
        await page.keyboard.press('ArrowUp');
        await expect(page.locator('#pd-search')).toBeFocused();
    });

    test('a query survives the payload arriving late', async ({ page }) => {
        let release!: () => void;
        const gate = new Promise<void>((r) => { release = r; });
        await page.unroute('**/api/v1/map/state-candidates*');
        await page.route('**/api/v1/map/state-candidates*', async (route) => {
            await gate;
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(OHIO_PAYLOAD) });
        });

        await openMap(page);
        await selectState(page, 'Ohio').catch(() => {});
        await page.locator('#pd-search').fill('12');
        release();
        await expect(page.locator('#panel-districts .dist-row', { hasText: 'Troy Balderson' })).toBeVisible();
        await expect(page.locator('#pd-search')).toHaveValue('12');
        await expect(page.locator('#pd-list .dist-row:not([hidden])')).toHaveCount(1);
    });
});

test.describe('candidate drawer: source and reporting (desktop)', () => {
    test.use({ viewport: { width: 1440, height: 900 }, timezoneId: 'America/Los_Angeles' });

    test.beforeEach(async ({ page }) => {
        await page.addInitScript(() => localStorage.removeItem('u9_map_sc_OH'));
        await stubOhio(page);
    });

    async function openCasey(page: Page) {
        await openMap(page);
        await selectState(page, 'Ohio');
        await page.locator('#panel-districts .dist-row').nth(2).click();
        await page.locator('#panel-candidates .candidate-card', { hasText: 'Casey Challenger' }).click();
        await expect(page.locator('#pol-provenance')).toBeVisible();
    }

    test('shows where the data came from and how fresh it is', async ({ page }) => {
        await openCasey(page);
        const stamp = page.locator('#pol-provenance .dr-line');
        await expect(stamp).toContainText('Source: News discovery (unverified)');
        await expect(stamp).toContainText('Updated 3 days ago');
    });

    test('a visitor can report a problem; the report says which card it was about', async ({ page }) => {
        let body: any = null;
        await page.route('**/api/v1/data-reports', async (route) => {
            body = route.request().postDataJSON();
            await route.fulfill({ status: 201, contentType: 'application/json', body: '{"ok":true}' });
        });
        await openCasey(page);

        await page.getByRole('button', { name: 'Report a data problem' }).click();
        await page.getByLabel('What looks wrong?').selectOption('not_a_person');
        await page.getByLabel('Details (optional)').fill('Not on the ballot.');
        await page.getByRole('button', { name: 'Send report' }).click();

        await expect(page.locator('#pol-provenance .dr-thanks')).toBeVisible();
        expect(body).toMatchObject({
            subject_type: 'election_candidate_record',
            subject_name: 'Casey Challenger',
            state: 'OH',
            problem: 'not_a_person',
            message: 'Not on the ballot.',
            source_label: 'News discovery (unverified)',
        });
        expect(body.website).toBe('');
    });

    test('a failed send keeps the form and says why', async ({ page }) => {
        await page.route('**/api/v1/data-reports', (route) => route.fulfill({ status: 429, body: '{}' }));
        await openCasey(page);
        await page.getByRole('button', { name: 'Report a data problem' }).click();
        await page.getByRole('button', { name: 'Send report' }).click();

        await expect(page.locator('#pol-provenance .dr-error')).toContainText('try again in a minute');
        await expect(page.getByRole('button', { name: 'Send report' })).toBeEnabled();
    });
});
