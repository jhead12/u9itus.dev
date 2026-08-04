import { test, expect } from '@playwright/test';

/**
 * Exercises the Wix-paste formatting bug end to end: a citizen pastes
 * page-builder markup (inline colors, empty spacer paragraphs, &nbsp;) into
 * the Quill post editor, and we verify both halves of the fix —
 * editor.js's paste-time cleanup (what the author sees while composing) and
 * Post::sanitizeBody()'s empty-paragraph stripping (what actually gets
 * saved and rendered publicly).
 */

const TEST_EMAIL = 'e2e-blog-test@example.com';
const TEST_PASSWORD = 'password';

// Representative of what Wix's clipboard HTML actually looks like: inline
// colors/fonts, and empty <p> elements used as manual visual spacers.
const WIX_HTML = `
  <div>
    <p style="color: rgb(66, 103, 178); font-family: Arial, sans-serif;">
      <span style="color: rgb(66, 103, 178);">First paragraph from Wix.</span>
    </p>
    <p><br></p>
    <p>&nbsp;</p>
    <p style="text-align: center;"> </p>
    <p>Second paragraph from Wix.</p>
  </div>
`;

async function login(page) {
    await page.goto('/login');
    await page.fill('input[name="email"]', TEST_EMAIL);
    await page.fill('input[name="password"]', TEST_PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.includes('/login'));
}

async function pasteIntoEditor(page, html: string) {
    await page.click('#post-body-editor .ql-editor');
    await page.evaluate((pastedHtml) => {
        const editor = document.querySelector('#post-body-editor .ql-editor');
        const dataTransfer = new DataTransfer();
        dataTransfer.setData('text/html', pastedHtml);
        const event = new ClipboardEvent('paste', {
            bubbles: true,
            cancelable: true,
            clipboardData: dataTransfer,
        });
        editor?.dispatchEvent(event);
    }, html);
}

test('pasting Wix markup is cleaned in the composer and stays clean after save', async ({ page }) => {
    await login(page);

    await page.goto('/citizen/posts/create');
    await expect(page.locator('#post-body-editor .ql-editor')).toBeVisible();

    await pasteIntoEditor(page, WIX_HTML);

    // --- Client-side check: editor.js's paste handler should have already
    // stripped inline styles and empty spacer paragraphs before Quill even
    // renders them, so the composer doesn't show a misleading preview.
    const editorHtml = await page.locator('#post-body-editor .ql-editor').innerHTML();
    expect(editorHtml).not.toMatch(/style=/i);
    expect(editorHtml).not.toMatch(/<p>\s*<br\s*\/?>\s*<\/p>/i);
    expect(editorHtml).toContain('First paragraph from Wix.');
    expect(editorHtml).toContain('Second paragraph from Wix.');

    // Save as a draft.
    const title = `Wix paste test ${Date.now()}`;
    await page.fill('input[name="title"]', title);
    await page.click('button[form="post-form"][type="submit"]');
    await page.waitForURL(/\/posts\/[^/]+\/edit$/);

    // --- Server-side check: reloading the edit page re-hydrates the editor
    // from Post::body as persisted by Post::sanitizeBody(). If the server
    // strip failed, the empty paragraphs/gap would reappear here.
    const savedHtml = await page.locator('#post-body-editor .ql-editor').innerHTML();
    expect(savedHtml).not.toMatch(/style=/i);
    expect(savedHtml).not.toMatch(/<p>\s*(<br\s*\/?>)?\s*<\/p>/i);

    const paragraphCount = await page.locator('#post-body-editor .ql-editor > p').count();
    expect(paragraphCount).toBe(2);

    // Publish and confirm the public page renders without the spacer gap.
    await page.click('#publish-form button[type="submit"]');
    await page.waitForLoadState('networkidle');

    const publicLink = page.locator('#view-public-link, a:has-text("View public page")');
    await expect(publicLink).toBeVisible();
    const publicHref = await publicLink.getAttribute('href');
    expect(publicHref).toBeTruthy();

    await page.goto(publicHref!);
    const bodyHtml = await page.locator('.prose').innerHTML();
    expect(bodyHtml).not.toMatch(/<p>\s*(<br\s*\/?>)?\s*<\/p>/i);
    expect(bodyHtml).toContain('First paragraph from Wix.');
    expect(bodyHtml).toContain('Second paragraph from Wix.');
});
