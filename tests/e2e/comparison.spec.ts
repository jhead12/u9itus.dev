import { test, expect } from '@playwright/test';

const candidates = ['Alex Rivera', 'Jamie Carter', 'Morgan Parker', 'Robin Nelson'].map((full_name, i) => ({
    key: `profile:${i + 1}`, full_name, party: 'Independent', incumbency: 'Challenger', candidacy: 'Running',
    profile_url: `https://example.com/profile/${i}`, source_label: 'Published profile', updated_at: '2026-09-19',
    stances: [{ topic: 'Housing', text: 'Build more affordable homes. '.repeat(12), source_url: `https://example.com/position/${i}`, source_label: 'Published statement' }],
}));
const payload = { available: true, seat: { label: 'U.S. House · CA-03', state: 'CA', office: 'U.S. Representative', district: 'CA-03' }, election: null, selected_key: 'profile:2', candidates };

test.beforeEach(async ({ page }) => {
    await page.route('**/api/v1/map/candidate-comparison?*', route => route.fulfill({ json: payload }));
});

test('search, selection, share restoration, and keyboard controls', async ({ page }, testInfo) => {
    await page.route('**/api/v1/map/politician-search?*', route => route.fulfill({ json: { results: [{ id: 2, full_name: 'Jamie Carter', state: 'CA', office: 'U.S. Representative', district: 'CA-03' }] } }));
    await page.goto('/compare');
    await page.getByLabel('State').selectOption('CA');
    await page.getByLabel('Search candidates').fill('Jamie');
    await page.getByRole('button', { name: /Jamie Carter —/ }).click();
    await expect(page.getByRole('checkbox', { name: 'Jamie Carter' })).toBeChecked();
    const third = page.getByRole('checkbox', { name: 'Morgan Parker' });
    await third.focus();
    await page.keyboard.press('Space');
    await expect(page.getByRole('checkbox', { name: 'Robin Nelson' })).toBeDisabled();
    await page.screenshot({ path: testInfo.outputPath('comparison-desktop.png'), fullPage: true });
    const shared = page.url();
    await page.goto(shared);
    await expect(page.getByRole('checkbox', { name: 'Morgan Parker' })).toBeChecked();
    await page.getByRole('button', { name: 'Copy comparison link' }).click();
    await expect(page.locator('#comparison-status').or(page.locator('#comparison-link-fallback')).filter({ hasText: /copied|Copy this link/ }).first()).toBeVisible();
});

test('missing selections are explicit and never replaced', async ({ page }) => {
    await page.goto('/compare?state=CA&district=CA-03&selected=profile:2,profile:999');
    await expect(page.locator('#comparison-content').getByText(/Selected records unavailable: profile:999/)).toBeVisible();
    await expect(page.locator('input[data-compare-key]:checked')).toHaveCount(1);
    await page.getByRole('button', { name: 'Remove unavailable selections' }).click();
    await expect(page).not.toHaveURL(/999/);
});

test('failed requests retry and mobile table scrolls without page overflow', async ({ page }, testInfo) => {
    let failures = 1;
    await page.route('**/api/v1/map/candidate-comparison?*', route => failures-- > 0 ? route.fulfill({ status: 503 }) : route.fulfill({ json: payload }));
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/compare?state=CA&district=CA-03');
    const consent = page.getByRole('button', { name: 'Decline non-essential' });
    if (await consent.isVisible()) await consent.click();
    await page.getByRole('button', { name: 'Try again' }).click();
    await expect(page.getByRole('table')).toBeVisible();
    expect(await page.locator('.compare-table-wrap').evaluate(el => el.scrollWidth > el.clientWidth)).toBeTruthy();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBeTruthy();
    await page.screenshot({ path: testInfo.outputPath('comparison-mobile.png'), fullPage: true });
});

test('print guide includes complete evidence, references, and QR on Letter and A4', async ({ page }, testInfo) => {
    const longPayload = structuredClone(payload);
    longPayload.candidates.forEach(c => { c.stances = Array.from({ length: 8 }, (_, i) => ({ ...c.stances[0], topic: `Issue ${i + 1}` })); });
    await page.route('**/api/v1/map/candidate-comparison?*', route => route.fulfill({ json: longPayload }));
    await page.goto('/compare?state=CA&district=CA-03&selected=profile:1,profile:2,profile:3');
    await expect(page.getByRole('table')).toBeVisible();
    await page.locator('#comparison-qr').evaluate(async (img: HTMLImageElement) => img.decode());
    await page.emulateMedia({ media: 'print' });
    await expect(page.locator('.compare-picker')).toBeHidden();
    await expect(page.locator('#u9-cookie-consent')).toBeHidden();
    await expect(page.locator('#comparison-sources li')).toHaveCount(6);
    await expect(page.locator('#comparison-qr')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'How do I use this guide?' })).toBeVisible();
    await expect(page.locator('#comparison-print-guide .guide-section')).toHaveCount(10);
    expect(await page.locator('.guide-table').first().evaluate(el => getComputedStyle(el).fontSize)).toBe('16px');
    await expect(page.locator('#comparison-content')).toBeHidden();
    for (const format of ['Letter', 'A4'] as const) {
        await page.pdf({ path: testInfo.outputPath(`comparison-${format}.pdf`), format, printBackground: true });
    }
});

test('typing a district code searches that district without requiring a name', async ({ page }) => {
    await page.route('**/api/v1/map/politician-search?*', route => {
        const url = new URL(route.request().url());
        expect(url.searchParams.get('mode')).toBe('district');
        expect(url.searchParams.get('q')).toBe('3');
        return route.fulfill({ json: { district_label: 'CA-03', results: [{ id: 2, full_name: 'Jamie Carter', state: 'CA', office: 'U.S. Representative', district: 'CA-03' }] } });
    });
    await page.goto('/compare');
    await page.getByLabel('State').selectOption('CA');
    await page.getByLabel('Search candidates').fill('3');
    await expect(page.locator('#comparison-search-status')).toContainText('CA-03');
    await page.getByRole('button', { name: /Jamie Carter —/ }).click();
    await expect(page.getByRole('checkbox', { name: 'Jamie Carter' })).toBeChecked();
});

test('address finder filters to the resolved district and Show all clears it', async ({ page }) => {
    await page.route('**/api/v1/map/politician-search?*', route => {
        const url = new URL(route.request().url());
        expect(url.searchParams.get('mode')).toBe('address');
        return route.fulfill({ json: { district_label: 'TX-06', results: [{ id: 3, full_name: 'Morgan Parker', state: 'TX', office: 'U.S. Representative', district: 'TX-06' }] } });
    });
    await page.goto('/compare');
    await page.getByLabel('Find by address — locates their district').fill('101 W Abram St, Arlington, TX');
    await page.getByRole('button', { name: 'Find', exact: true }).click();
    await expect(page.locator('#comparison-search-status')).toContainText('TX-06');
    await expect(page.getByRole('button', { name: /Morgan Parker —/ })).toBeVisible();
    await page.getByRole('button', { name: 'Show all' }).click();
    await expect(page.getByRole('button', { name: /Morgan Parker —/ })).toBeHidden();
    await expect(page.getByLabel('Find by address — locates their district')).toHaveValue('');
});

test('choosing a state lists races with running candidates and opens one preselected', async ({ page }) => {
    await page.route('**/api/v1/map/candidate-races?*', route => route.fulfill({ json: { state: 'CA', races: [{
        label: 'U.S. House · CA-03', office: 'U.S. Representative', district: 'CA-03', city: null,
        election: { date: '2026-11-03', stage: 'General' },
        running: [{ key: 'profile:1', full_name: 'Alex Rivera', party: 'Independent' }, { key: 'profile:3', full_name: 'Morgan Parker', party: null }],
        params: { state: 'CA', office: 'U.S. Representative', district: 'CA-03', selected: 'profile:1,profile:3' },
    }] } }));
    await page.goto('/compare');
    await page.getByLabel('State').selectOption('CA');
    await expect(page.locator('#comparison-search-status')).toContainText('1 race in CA with running candidates');
    const race = page.getByRole('button', { name: /U\.S\. House · CA-03/ });
    await expect(race).toContainText('General · November 3, 2026');
    await expect(race).toContainText('2 running: Alex Rivera (Independent), Morgan Parker');
    await race.click();
    await expect(page.getByRole('checkbox', { name: 'Alex Rivera' })).toBeChecked();
    await expect(page.getByRole('checkbox', { name: 'Morgan Parker' })).toBeChecked();
    await expect(page.getByRole('checkbox', { name: 'Jamie Carter' })).not.toBeChecked();
});
