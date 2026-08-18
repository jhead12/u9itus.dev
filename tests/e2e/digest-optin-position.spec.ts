import { test, expect } from '@playwright/test';

/**
 * Verifies the weekly-digest opt-in toast (resources/js/map/ui/digest-optin.js)
 * anchors itself near the favorite star that triggered it instead of always
 * appearing pinned to the bottom-right corner of the viewport.
 */

test('digest opt-in toast appears near the district favorite star, not pinned to the corner', async ({ page }) => {
    // District-mesh readiness after __mapGoTo can be slow/flaky under a
    // headless browser (see the retry loop below) — give it real headroom
    // beyond the config's default 30s.
    test.setTimeout(90000);

    // Suppress the first-visit guided tour — same key the app sets once a
    // real user finishes or skips it (see candidate-follow.spec.ts).
    await page.addInitScript(() => localStorage.setItem('u9_map_tour_v1', '1'));

    await page.goto('/map');
    await page.waitForSelector('#btn-search', { state: 'visible', timeout: 20000 });
    await page.waitForFunction(() => typeof window.__mapGoTo === 'function', undefined, { timeout: 20000 });

    const favBtn = page.locator('#panel-fav-btn');

    // window.__mapGoTo's district lookup races the async district-mesh
    // build (see resources/js/map/navigation/deep-link.js) and can give up
    // before the meshes exist — retry the navigation itself, not just the
    // wait, until the district panel (and its star) actually shows up.
    await expect(async () => {
        await page.evaluate(() => (window as any).__mapGoTo('TX', '13'));
        await expect(favBtn).toBeVisible({ timeout: 4000 });
    }).toPass({ timeout: 60000 });

    const favBox = await favBtn.boundingBox();
    expect(favBox).not.toBeNull();

    await favBtn.click();

    const toast = page.locator('.digest-optin-toast');
    await expect(toast).toBeVisible({ timeout: 5000 });

    // Should be positioned relative to the button (top/left set inline by
    // positionToast), not the fixed bottom-right fallback corner.
    await expect(toast).not.toHaveClass(/digest-optin-toast--fallback/);

    const toastBox = await toast.boundingBox();
    expect(toastBox).not.toBeNull();

    // "Near" the button: within a small margin below/above it, not clear
    // across the viewport at the opposite corner.
    const verticalGap = Math.min(
        Math.abs(toastBox!.y - favBox!.y - favBox!.height),
        Math.abs(favBox!.y - toastBox!.y - toastBox!.height)
    );
    expect(verticalGap).toBeLessThan(40);

    // Unfollow so a repeat run starts clean.
    await favBtn.click();
});
