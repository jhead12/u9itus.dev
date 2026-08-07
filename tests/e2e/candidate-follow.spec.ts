import { test, expect } from '@playwright/test';

/**
 * Verifies the candidate-follow feature end to end in both places it was
 * wired up: the politician public profile page (favorite-toggle partial)
 * and the map's candidate drawer (star button). The backend
 * (voter_favorite_politicians, FavoriteController) already existed with no
 * UI to trigger it — this exercises the new UI against the real endpoints.
 */

const TEST_EMAIL = 'e2e-voter-follow-test@example.com';
const TEST_PASSWORD = 'password';
const CANDIDATE_SLUG = 'e2e-follow-test-candidate';
const CANDIDATE_NAME = 'E2E Follow Test Candidate';

async function login(page) {
    await page.goto('/login');
    await page.fill('input[name="email"]', TEST_EMAIL);
    await page.fill('input[name="password"]', TEST_PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.includes('/login'));
}

test('voter can follow and unfollow a candidate from the profile page', async ({ page }) => {
    await login(page);
    await page.goto(`/p/${CANDIDATE_SLUG}`);

    const toggleForm = page.locator('form[data-favorite-toggle]');
    await expect(toggleForm).toBeVisible();
    const button = toggleForm.locator('button[type="submit"]');
    await expect(button).toHaveText(/Follow/);

    await button.click();
    await expect(button).toHaveText('Following', { timeout: 5000 });

    // Reload to confirm it's persisted server-side, not just client state.
    await page.reload();
    await expect(page.locator('form[data-favorite-toggle] button[type="submit"]')).toHaveText('Following');

    // Unfollow.
    await page.locator('form[data-favorite-toggle] button[type="submit"]').click();
    await expect(page.locator('form[data-favorite-toggle] button[type="submit"]')).toHaveText('Follow', { timeout: 5000 });

    await page.reload();
    await expect(page.locator('form[data-favorite-toggle] button[type="submit"]')).toHaveText('Follow');
});

test('guest sees a sign-in CTA instead of a follow button on the profile page', async ({ page }) => {
    await page.goto(`/p/${CANDIDATE_SLUG}`);
    await expect(page.locator('form[data-favorite-toggle]')).toHaveCount(0);
    await expect(page.getByText('Sign in to follow this candidate')).toBeVisible();
});

test('voter can follow a candidate from the map drawer star', async ({ page }) => {
    await login(page);

    // Suppress the first-visit guided tour (tour.js auto-launches it after
    // 2s and its backdrop blocks clicks) — same key the app itself sets
    // once a real user finishes or skips it.
    await page.addInitScript(() => localStorage.setItem('u9_map_tour_v1', '1'));

    await page.goto('/map');
    await page.waitForSelector('#btn-search', { state: 'visible', timeout: 20000 });

    // Open search, jump to the test candidate, drawer should open automatically.
    await page.click('#btn-search');
    await page.fill('#search-input', CANDIDATE_NAME);

    // toBeVisible polls until the debounced search results render.
    const resultItem = page.locator('#search-results .sr-item', { hasText: CANDIDATE_NAME }).first();
    await expect(resultItem).toBeVisible({ timeout: 10000 });
    await resultItem.click();

    const drawer = page.locator('#pol-drawer');
    await expect(drawer).toHaveClass(/open/, { timeout: 10000 });

    const followBtn = page.locator('#pol-drawer-follow-btn');
    await expect(followBtn).toBeVisible();
    await expect(followBtn).toHaveAttribute('aria-pressed', 'false');

    await followBtn.click();
    await expect(followBtn).toHaveAttribute('aria-pressed', 'true', { timeout: 5000 });
    await expect(followBtn).toHaveText('★');

    // Unfollow to leave the fixture account clean for repeat runs.
    await followBtn.click();
    await expect(followBtn).toHaveAttribute('aria-pressed', 'false', { timeout: 5000 });
});

test('guest clicking the map follow star gets the sign-in nudge, not a failed request', async ({ page }) => {
    await page.addInitScript(() => localStorage.setItem('u9_map_tour_v1', '1'));

    await page.goto('/map');
    await page.waitForSelector('#btn-search', { state: 'visible', timeout: 20000 });

    await page.click('#btn-search');
    await page.fill('#search-input', CANDIDATE_NAME);
    const resultItem = page.locator('#search-results .sr-item', { hasText: CANDIDATE_NAME }).first();
    await expect(resultItem).toBeVisible({ timeout: 10000 });
    await resultItem.click();

    const drawer = page.locator('#pol-drawer');
    await expect(drawer).toHaveClass(/open/, { timeout: 10000 });

    const followBtn = page.locator('#pol-drawer-follow-btn');
    await expect(followBtn).toBeVisible();

    await followBtn.click();

    // There's no guest-cookie path for candidate follows (unlike map
    // boundaries) — clicking as a guest should open the sign-in popover
    // instead of silently hitting (and failing) the voter-only API.
    await expect(page.locator('#earn-popover')).toHaveClass(/open/, { timeout: 5000 });
    await expect(followBtn).toHaveAttribute('aria-pressed', 'false');
});
