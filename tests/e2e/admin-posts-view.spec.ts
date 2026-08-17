import { test, expect } from '@playwright/test';

/**
 * Verifies the "View" button on /admin/posts: it should appear on
 * published posts and open the live public post page.
 */

const ADMIN_EMAIL = 'e2e-admin-test@example.com';
const ADMIN_PASSWORD = 'password';

async function login(page) {
    await page.goto('/login');
    await page.fill('input[name="email"]', ADMIN_EMAIL);
    await page.fill('input[name="password"]', ADMIN_PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.includes('/login'));
}

test('admin can view a published post from /admin/posts', async ({ page, context }) => {
    await login(page);

    await page.goto('/admin/posts?status=published');
    await expect(page.locator('table')).toBeVisible();

    const firstRow = page.locator('tbody tr').first();
    await expect(firstRow).toBeVisible();

    const viewLink = firstRow.locator('a:has-text("View")');
    await expect(viewLink).toBeVisible();
    expect(await viewLink.getAttribute('target')).toBe('_blank');

    const href = await viewLink.getAttribute('href');
    expect(href).toContain('/blog/');

    const [publicPage] = await Promise.all([
        context.waitForEvent('page'),
        viewLink.click(),
    ]);
    await publicPage.waitForLoadState();

    expect(publicPage.url()).toContain('/blog/');
    expect(publicPage.url()).not.toContain('/login');
    await expect(publicPage.locator('body')).not.toContainText('404');
});
