import { test, expect } from '@playwright/test';

// Explicit opt-in: these checks use real public geocoding services and API quota.
test.describe('map live providers', () => {
    test.skip(process.env.U9_LIVE_MAP !== '1', 'Set U9_LIVE_MAP=1 to use real providers');
    test.use({ viewport: { width: 1440, height: 900 }, timezoneId: 'America/Los_Angeles' });
    test.setTimeout(90000);

    async function openMap(page) {
        await page.goto('/map');
        await expect(page.locator('#btn-find-district')).toBeVisible();
        await page.waitForFunction(() => document.querySelectorAll('.state-label').length > 0, null, { timeout: 30000 });
        const consent = page.getByRole('button', { name: 'Got it', exact: true });
        if (await consent.isVisible()) await consent.click();
    }

    test('public street address resolves through the real map API', async ({ page }) => {
        await openMap(page);
        await page.locator('#btn-find-district').click();
        await page.locator('#fd-input').fill('4600 Sunset Ave, Indianapolis, IN 46208');
        const responsePromise = page.waitForResponse(r => r.url().endsWith('/map/geocode') && r.request().method() === 'POST');
        await page.locator('#fd-submit').click();
        const response = await responsePromise;
        const result = await response.json();
        expect(response.status(), JSON.stringify(result)).toBe(200);
        expect(result).toMatchObject({ district_code: 'IN-07', precision: 'address', boundary_congress: 119 });
        expect(result).not.toHaveProperty('matched_address');
        await expect(page.locator('#panel-state')).toContainText('District 7', { timeout: 60000 });
        expect(page.url()).not.toContain('Sunset');
    });

    test('real ZIP response either offers districts or asks for an address', async ({ page }) => {
        await openMap(page);
        await page.locator('#btn-find-district').click();
        await page.locator('#fd-input').fill('43215');
        const responsePromise = page.waitForResponse(r => r.url().endsWith('/map/geocode'));
        await page.locator('#fd-submit').click();
        const result = await (await responsePromise).json();
        console.log('Live ZIP outcome:', JSON.stringify(result));
        if (result.needs_address) {
            await expect(page.locator('#fd-status')).toContainText('full street address');
            await expect(page.locator('#find-district-card')).toBeVisible();
        } else {
            expect(result.ok).toBe(true);
            expect(result.ambiguous ? result.candidates.length > 1 : !!result.district_code).toBe(true);
        }
    });

    test('browser geolocation permission and coordinates reach the live provider', async ({ page, context }) => {
        await context.grantPermissions(['geolocation']);
        await context.setGeolocation({ latitude: 39.8409, longitude: -86.1710, accuracy: 10 });
        await openMap(page);
        await page.locator('#btn-find-district').click();
        const responsePromise = page.waitForResponse(r => r.url().endsWith('/map/geocode'));
        await page.locator('#fd-locate').click();
        const response = await responsePromise;
        expect(response.status()).toBe(200);
        expect(await response.json()).toMatchObject({ district_code: 'IN-07', precision: 'location', boundary_congress: 119 });
        await expect(page.locator('#panel-state')).toContainText('District 7', { timeout: 60000 });
    });
});
