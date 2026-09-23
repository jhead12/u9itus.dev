import { test, expect } from '@playwright/test';

/**
 * Verifies the "Find by city" district lookup on the Submit Source /
 * chatter-contributor candidate picker: geocoding an address should narrow
 * the politician <select> down to whoever represents that district. A bare
 * city name (no street) can't resolve via either Census or Google Civic's
 * divisionsByAddress — both need a specific point, and cities like Arlington,
 * TX span several congressional districts anyway — so this test uses a full
 * street address, which resolves via Census alone.
 */

const TEST_EMAIL = 'e2e-chatter-contributor-test@example.com';
const TEST_PASSWORD = 'password';

async function login(page) {
    await page.goto('/login');
    await page.fill('input[name="email"]', TEST_EMAIL);
    await page.fill('input[name="password"]', TEST_PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.includes('/login'));
}

test('contributor can find the candidate for an address via the city/district lookup', async ({ page }) => {
    await login(page);
    await page.goto('/contribute/chatter');

    const picker = page.locator('[data-politician-picker]');
    await expect(picker).toBeVisible();

    const select = picker.locator('[data-politician-results]');
    const cityInput = picker.locator('[data-district-city]');
    const stateInput = picker.locator('[data-district-state]');
    const findBtn = picker.locator('[data-district-find]');
    const status = picker.locator('[data-district-status]');

    const initialOptionCount = await select.locator('option').count();
    expect(initialOptionCount).toBeGreaterThan(1);

    await cityInput.fill('101 W Abram St, Arlington');
    await stateInput.fill('TX');
    await findBtn.click();

    await expect(status).toContainText('TX 6th Congressional District', { timeout: 15000 });

    const options = select.locator('option:not([value=""])');
    await expect(options).toHaveCount(1, { timeout: 5000 });
    await expect(options.first()).toContainText('Jake Ellzey');

    // "Show all" clears the filter back to the full candidate list.
    await picker.locator('[data-district-clear]').click();
    await expect(select.locator('option')).toHaveCount(initialOptionCount);
});

test('an unresolvable city shows a message instead of silently filtering', async ({ page }) => {
    await login(page);
    await page.goto('/contribute/chatter');

    const picker = page.locator('[data-politician-picker]');
    await picker.locator('[data-district-city]').fill('Nowhereville');
    await picker.locator('[data-district-find]').click();

    const status = picker.locator('[data-district-status]');
    await expect(status).toContainText(/could not resolve/i, { timeout: 15000 });
});
