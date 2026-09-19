import { test, expect } from '@playwright/test';

/**
 * Homepage refresh: one hero action, honest candidate labelling, compact
 * candidate cards, plain-language sections, hidden rewards/civic-identity
 * sections, and a compact cookie notice on narrow screens.
 *
 * Needs at least a few published politicians in the database. Geo lookup is
 * server-side (ipinfo) and can't resolve localhost, so the page is expected to
 * fall back to the nationwide label here; the local-label logic itself is
 * covered by tests/Feature/Web/HomepageFeaturedCandidatesTest.php.
 */

const MOBILE = { width: 375, height: 667 };
const DESKTOP = { width: 1280, height: 900 };

/** The hero fades in with a translateY slide; measure only once it has settled. */
async function settle(page) {
    await page.waitForFunction(() => document.getAnimations().every((a) => a.playState !== 'running'));
}

for (const [name, viewport] of [['mobile', MOBILE], ['desktop', DESKTOP]] as const) {
    test.describe(`homepage (${name})`, () => {
        test.use({ viewport });

        test.beforeEach(async ({ page }) => {
            await page.goto('/');
            await settle(page);
        });

        test('hero has one dominant action and no account button', async ({ page }) => {
            await expect(page.locator('h1')).toContainText("Know who's running.");
            await expect(page.locator('h1')).toContainText('Understand where they stand.');

            const hero = page.locator('section').first();
            const primary = hero.getByRole('link', { name: 'Find My District' });
            await expect(primary).toBeVisible();
            await expect(hero.getByRole('link', { name: 'Create Free Account' })).toHaveCount(0);

            // Secondary paths are quiet text links, not competing buttons.
            await expect(hero.getByRole('link', { name: 'Browse candidates' })).toBeVisible();
            await expect(hero.getByRole('link', { name: 'Explore the map' })).toBeVisible();
        });

        test('candidate section never claims "near you" without local matches', async ({ page }) => {
            const section = page.locator('#featured-candidates');
            await expect(section).toBeVisible();
            await expect(section).not.toContainText('Candidates Near You');

            const nearYouChips = await section.getByText('Near you', { exact: true }).count();
            if (nearYouChips === 0) {
                await expect(section).toContainText('Featured nationwide');
            }
        });

        test('candidate cards are compact and fall back to initials for broken photos', async ({ page }) => {
            const cards = page.locator('#featured-candidates a[href*="/p/"]');
            await expect(cards.first()).toBeVisible();
            expect(await cards.count()).toBeGreaterThanOrEqual(2);

            // Seeded photo URLs are unreachable; onerror removes the <img>, leaving initials.
            await expect(page.locator('#featured-candidates a[href*="/p/"] img')).toHaveCount(0, { timeout: 10_000 });

            const box = await cards.first().boundingBox();
            expect(box).not.toBeNull();
            // The old card had a 16:10 hero image; a compact card stays well under that.
            expect(box!.height).toBeLessThan(230);

            // Name, office/status info is inside the first viewport-height of the card.
            await expect(cards.first().locator('h3')).toBeVisible();
        });

        test('long names do not push cards past the viewport', async ({ page }) => {
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
            expect(overflow).toBeLessThanOrEqual(0);

            const cards = page.locator('#featured-candidates a[href*="/p/"]');
            const count = await cards.count();
            for (let i = 0; i < count; i++) {
                const box = await cards.nth(i).boundingBox();
                expect(box!.x + box!.width).toBeLessThanOrEqual(viewport.width);
            }
        });

        test('status chips reflect incumbent vs candidate', async ({ page }) => {
            const section = page.locator('#featured-candidates');
            const chips = await section.locator('span.rounded-full').allInnerTexts();
            expect(chips.some((t) => t === 'Incumbent' || t === 'Candidate')).toBe(true);
        });

        test('internal jargon and hidden sections are gone', async ({ page }) => {
            const body = page.locator('body');
            await expect(body).not.toContainText('Phase 3');
            await expect(body).not.toContainText('Growth Loop');
            await expect(body).not.toContainText('Transparency Layer');
            await expect(body).not.toContainText('Your Civic Identity');
            await expect(body).not.toContainText('Now It Pays You');
            await expect(page.locator('#revenue')).toHaveCount(0);
            await expect(page.locator('#civic-identity')).toHaveCount(0);

            await expect(page.locator('#sources')).toContainText('Check their sources');
        });

        test('sections appear in the planned order', async ({ page }) => {
            const ids = ['featured-candidates', 'how-it-works', 'sources'];
            const tops: number[] = [];
            for (const id of ids) {
                const box = await page.locator(`#${id}`).boundingBox();
                expect(box, `#${id} should render`).not.toBeNull();
                tops.push(box!.y);
            }
            expect([...tops].sort((a, b) => a - b)).toEqual(tops);
        });

        test('footer links point at sections that exist', async ({ page }) => {
            const hrefs = await page.locator('footer a[href^="#"]').evaluateAll((els) =>
                els.map((el) => el.getAttribute('href')!.slice(1)).filter(Boolean),
            );
            expect(hrefs.length).toBeGreaterThan(0);
            for (const id of hrefs) {
                await expect(page.locator(`#${id}`), `footer links to #${id}`).toHaveCount(1);
            }
        });
    });
}

test.describe('cookie notice', () => {
    test('is compact on mobile, leaves the hero action clear, and can be dismissed', async ({ page }) => {
        await page.setViewportSize(MOBILE);
        await page.goto('/');
        await settle(page);

        const banner = page.locator('#u9-cookie-consent');
        await expect(banner).toBeVisible();

        const bannerBox = (await banner.boundingBox())!;
        expect(bannerBox.height).toBeLessThan(170);

        const cta = page
            .locator('section')
            .first()
            .getByRole('link', { name: 'Find My District' });
        const ctaBox = (await cta.boundingBox())!;
        expect(ctaBox.y + ctaBox.height).toBeLessThan(bannerBox.y);

        // Both choices stay on screen and usable.
        await expect(banner.getByRole('button', { name: 'Decline non-essential' })).toBeVisible();
        await banner.getByRole('button', { name: 'Got it' }).click();
        await expect(banner).toHaveCount(0);
    });
});
