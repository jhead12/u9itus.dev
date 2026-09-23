import { test, expect } from '@playwright/test';

/**
 * Verifies the "Government Directory" sidebar link added to the voter
 * dashboard (Explore section) — a static external link out to CivLab's
 * civic-data graph (https://graph.civlab.org/us). No backend behavior to
 * exercise here, just that it renders correctly and opens in a new tab
 * rather than navigating the app away.
 */

const TEST_EMAIL = 'e2e-voter-follow-test@example.com';
const TEST_PASSWORD = 'password';

async function login(page) {
    await page.goto('/login');
    await page.fill('input[name="email"]', TEST_EMAIL);
    await page.fill('input[name="password"]', TEST_PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.includes('/login'));
}

test('voter dashboard sidebar shows a Government Directory link that opens CivLab in a new tab', async ({ page }) => {
    await login(page);
    await page.goto('/voter/dashboard');

    // Assert on the link's own attributes rather than actually following it —
    // graph.civlab.org is a third party we don't control the availability of.
    const link = page.locator('a[href="https://graph.civlab.org/us"]');
    await expect(link).toBeVisible();
    await expect(link).toHaveText(/Government Directory/);
    await expect(link).toHaveAttribute('target', '_blank');
    await expect(link).toHaveAttribute('rel', /noopener/);
});
