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
