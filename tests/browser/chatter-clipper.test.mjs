import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';
import { publicSourceUrl } from '../../browser-extension/chatter-clipper/source-url.js';

const root = new URL('../../', import.meta.url);
const read = path => readFileSync(new URL(path, root), 'utf8');
const clip = { version: 1, source_url: 'https://x.com/example/status/123', headline: 'A source <script>alert(1)</script>', source_excerpt: 'Selected passage' };
const fragment = '#clip=' + encodeURIComponent(JSON.stringify({ ...clip, politician_id: 777, moderation_status: 'published', public_source: true }));
const key = 'u9itus.chatter.clip.v1';
const form = restore => `<p data-clip-notice hidden role="status"></p><form data-chatter-clip-form data-restore-clip="${restore}">
  <input name="source_url"><input name="headline" value="My existing edit"><textarea name="source_excerpt"></textarea>
  <select name="platform"><option value="news">News</option><option value="x">X</option></select>
  <select name="politician_id"><option value="">Choose</option><option value="777">Someone</option></select>
  <input name="public_source" type="checkbox"><textarea name="contributor_notes"></textarea>
</form><script type="module" src="/js/chatter-clip-import.js"></script>`;

test('extension permissions and URL screening stay limited', () => {
    const manifest = JSON.parse(read('browser-extension/chatter-clipper/manifest.json'));
    assert.deepEqual(manifest.permissions, ['activeTab', 'scripting', 'contextMenus']);
    assert.deepEqual(manifest.background, { service_worker: 'background.js' });
    for (const field of ['host_permissions', 'content_scripts', 'externally_connectable']) assert.equal(manifest[field], undefined);
    for (const url of ['https://x.com/person/status/123', 'https://publication.substack.com/p/news', 'https://youtu.be/123']) assert.equal(publicSourceUrl(url), true);
    for (const url of ['javascript:alert(1)', 'file:///secret', 'chrome://settings', 'http://127.1/a', 'http://10.0.0.1/a', 'http://[::1]/', 'https://localhost/a', 'https://service.local/a', 'https://name:pass@example.com/a', 'https://x.com/messages/1', 'https://instagram.com/direct/inbox', 'https://mail.google.com/mail/u/0', 'https://example.com?access_token=secret']) assert.equal(publicSourceUrl(url), false, url);
    assert.equal(read('public/js/chatter-source-url.js'), read('browser-extension/chatter-clipper/source-url.js'));
});

test('clipping survives sign-in, imports only draft fields and never submits', async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage();
        let signedIn = false;
        const requests = [];
        await page.route('https://www.u9itus.com/**', async route => {
            const path = new URL(route.request().url()).pathname;
            requests.push({ url: route.request().url(), method: route.request().method() });
            if (path.startsWith('/js/')) return route.fulfill({ contentType: 'text/javascript', body: read('public' + path) });
            if (path.endsWith('/clip')) return route.fulfill({ contentType: 'text/html', body: '<p data-clip-handoff></p><script type="module" src="/js/chatter-clip-import.js"></script>' });
            if (path === '/login') return route.fulfill({ contentType: 'text/html', body: '<h1>Sign in</h1>' });
            if (!signedIn) return route.fulfill({ status: 302, headers: { location: '/login' } });
            return route.fulfill({ contentType: 'text/html', body: form(true) });
        });
        await page.goto('https://www.u9itus.com/contribute/chatter/clip' + fragment);
        await page.waitForURL('**/login');
        assert.equal(await page.evaluate(() => location.hash), '');
        assert.ok(await page.evaluate(key => sessionStorage.getItem(key), key));
        signedIn = true;
        await page.goto('https://www.u9itus.com/contribute/chatter');
        await page.waitForFunction(() => document.querySelector('[name=source_url]').value !== '');
        assert.equal(await page.locator('[name=headline]').inputValue(), clip.headline);
        assert.equal(await page.locator('[name=source_excerpt]').inputValue(), clip.source_excerpt);
        assert.equal(await page.locator('[name=platform]').inputValue(), 'x');
        assert.equal(await page.locator('[name=politician_id]').inputValue(), '');
        assert.equal(await page.locator('[name=public_source]').isChecked(), false);
        assert.equal(await page.evaluate(key => sessionStorage.getItem(key), key), null);
        assert.ok(requests.every(request => request.method === 'GET' && !request.url.includes('#') && !request.url.includes('Selected')));
        await page.reload();
        assert.equal(await page.locator('[name=source_url]').inputValue(), '');
    } finally { await browser.close(); }
});

test('expired, invalid and validation-recovery drafts cannot overwrite form values', async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage();
        let restore = true;
        await page.route('https://www.u9itus.com/**', route => {
            const path = new URL(route.request().url()).pathname;
            return route.fulfill({ contentType: path.startsWith('/js/') ? 'text/javascript' : 'text/html', body: path.startsWith('/js/') ? read('public' + path) : form(restore) });
        });
        await page.goto('https://www.u9itus.com/contribute/chatter');
        for (const data of [JSON.stringify({ clip, createdAt: Date.now() - 31 * 60000 }), '{bad json', JSON.stringify({ clip: { ...clip, source_url: 'javascript:alert(1)' }, createdAt: Date.now() })]) {
            await page.evaluate(({ key, data }) => sessionStorage.setItem(key, data), { key, data });
            await page.reload();
            await page.waitForFunction(() => !document.querySelector('[data-clip-notice]').hidden);
            assert.equal(await page.locator('[name=headline]').inputValue(), 'My existing edit');
        }
        restore = false;
        await page.evaluate(({ key, clip }) => sessionStorage.setItem(key, JSON.stringify({ clip, createdAt: Date.now() })), { key, clip });
        await page.reload();
        assert.equal(await page.locator('[name=headline]').inputValue(), 'My existing edit');
        assert.equal(await page.evaluate(key => sessionStorage.getItem(key), key), null);
    } finally { await browser.close(); }
});

test('popup previews selected text and opens only a confirmed first-party draft', async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage();
        await page.addInitScript(() => {
            window.createdTabs = [];
            window.chrome = {
                tabs: { query: async () => [{ id: 1, url: 'https://example.com/article', title: 'Example headline' }], create: async data => { window.createdTabs.push(data); } },
                scripting: { executeScript: async () => [{ result: 'Selected passage' }] },
            };
            window.close = () => {};
        });
        await page.route('https://clipper.test/**', route => {
            const path = new URL(route.request().url()).pathname.slice(1) || 'popup.html';
            return route.fulfill({ contentType: path.endsWith('.js') ? 'text/javascript' : path.endsWith('.css') ? 'text/css' : 'text/html', body: read('browser-extension/chatter-clipper/' + path) });
        });
        await page.goto('https://clipper.test/popup.html');
        await page.waitForFunction(() => !document.querySelector('fieldset').disabled);
        assert.equal(await page.locator('#excerpt').inputValue(), 'Selected passage');
        await page.locator('#continue').click();
        assert.equal(await page.evaluate(() => window.createdTabs.length), 0);
        await page.locator('#public-source').check();
        await page.locator('#continue').click();
        const [created] = await page.evaluate(() => window.createdTabs);
        assert.equal(new URL(created.url).origin, 'https://www.u9itus.com');
        assert.equal(new URL(created.url).pathname, '/contribute/chatter/clip');
        const data = JSON.parse(decodeURIComponent(new URL(created.url).hash.slice(6)));
        assert.equal(data.source_excerpt, 'Selected passage');
        assert.equal(data.headline, 'Example headline');
        assert.equal(data.politician_id, undefined);
    } finally { await browser.close(); }
});

test('right-click menu hands off exactly the clicked page/selection, nothing more', async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage();
        await page.addInitScript(() => {
            window.createdTabs = [];
            window.registeredMenus = [];
            window.chrome = {
                runtime: { onInstalled: { addListener: fn => fn() }, getURL: path => 'chrome-extension://fake-id/' + path },
                contextMenus: { create: options => window.registeredMenus.push(options), onClicked: { addListener: fn => { window.__onClicked = fn; } } },
                tabs: { create: async data => { window.createdTabs.push(data); } },
            };
        });
        await page.route('https://clipper.test/**', route => route.fulfill({
            contentType: 'text/javascript', body: read('browser-extension/chatter-clipper/background.js'),
        }));
        await page.goto('about:blank');
        await page.addScriptTag({ url: 'https://clipper.test/background.js' });
        await page.waitForFunction(() => window.registeredMenus?.length === 1);
        const menu = await page.evaluate(() => window.registeredMenus[0]);
        assert.equal(menu.title, 'Clip to U9itus');
        assert.deepEqual(menu.contexts, ['page', 'selection']);

        // A click on an unrelated menu item (only relevant once other items ever exist) must no-op.
        await page.evaluate(() => window.__onClicked({ menuItemId: 'something-else', selectionText: 'x' }, { url: 'https://example.com', title: 'Example' }));
        assert.equal(await page.evaluate(() => window.createdTabs.length), 0);

        await page.evaluate(() => window.__onClicked(
            { menuItemId: window.registeredMenus[0].id, selectionText: '  Selected passage  ' },
            { url: 'https://example.com/article', title: 'Example headline' },
        ));
        const [created] = await page.evaluate(() => window.createdTabs);
        assert.equal(created.url.startsWith('chrome-extension://fake-id/popup.html#from-menu='), true);
        const data = JSON.parse(decodeURIComponent(created.url.split('#from-menu=')[1]));
        assert.deepEqual(data, { url: 'https://example.com/article', title: 'Example headline', excerpt: '  Selected passage  ' });

        // No tab and no selection must not throw, and must still not act on a non-clip menu id.
        await page.evaluate(() => window.__onClicked({ menuItemId: 'u9itus-clip', selectionText: undefined }, undefined));
        assert.equal(await page.evaluate(() => window.createdTabs.length), 1);
    } finally { await browser.close(); }
});

test('popup imports a right-click clip without touching chrome.tabs, and clears the fragment', async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage();
        await page.addInitScript(() => {
            window.tabsQueried = false;
            window.chrome = {
                tabs: { query: async () => { window.tabsQueried = true; return []; }, create: async () => {} },
                scripting: { executeScript: async () => { throw new Error('must not run for a right-click clip'); } },
            };
        });
        await page.route('https://clipper.test/**', route => {
            const path = new URL(route.request().url()).pathname.slice(1) || 'popup.html';
            return route.fulfill({ contentType: path.endsWith('.js') ? 'text/javascript' : path.endsWith('.css') ? 'text/css' : 'text/html', body: read('browser-extension/chatter-clipper/' + path) });
        });
        const payload = { url: 'https://x.com/example/status/1', title: 'A post', excerpt: 'Highlighted text' };
        const hash = '#from-menu=' + encodeURIComponent(JSON.stringify(payload));
        await page.goto('https://clipper.test/popup.html' + hash);
        await page.waitForFunction(() => !document.querySelector('fieldset').disabled);
        assert.equal(await page.locator('#source-url').inputValue(), payload.url);
        assert.equal(await page.locator('#headline').inputValue(), payload.title);
        assert.equal(await page.locator('#excerpt').inputValue(), payload.excerpt);
        assert.equal(await page.evaluate(() => window.tabsQueried), false);
        assert.equal(await page.evaluate(() => window.location.hash), '');
        assert.equal(await page.evaluate(() => document.documentElement.classList.contains('standalone')), true);
    } finally { await browser.close(); }
});

test('popup rejects a right-click clip for a non-public URL instead of populating the form', async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage();
        await page.addInitScript(() => { window.chrome = { tabs: { query: async () => [] } }; });
        await page.route('https://clipper.test/**', route => {
            const path = new URL(route.request().url()).pathname.slice(1) || 'popup.html';
            return route.fulfill({ contentType: path.endsWith('.js') ? 'text/javascript' : path.endsWith('.css') ? 'text/css' : 'text/html', body: read('browser-extension/chatter-clipper/' + path) });
        });
        const hash = '#from-menu=' + encodeURIComponent(JSON.stringify({ url: 'https://mail.google.com/mail/u/0', title: 'Inbox', excerpt: '' }));
        await page.goto('https://clipper.test/popup.html' + hash);
        await page.waitForFunction(() => document.querySelector('#status').classList.contains('error'));
        assert.equal(await page.locator('#source-url').isDisabled(), true);
        assert.match(await page.locator('#status').textContent(), /public article or social post/);
    } finally { await browser.close(); }
});

test('unpacked extension installs in Chromium and displays its popup', async () => {
    const extensionPath = fileURLToPath(new URL('browser-extension/chatter-clipper', root));
    const profile = mkdtempSync(join(tmpdir(), 'u9itus-clipper-browser-'));
    const context = await chromium.launchPersistentContext(profile, {
        channel: 'chromium', headless: true,
        args: [`--disable-extensions-except=${extensionPath}`, `--load-extension=${extensionPath}`],
    });
    try {
        const page = await context.newPage();
        await page.goto('chrome://extensions');
        const item = page.locator('extensions-item').filter({ hasText: 'U9itus Source Clipper' });
        await item.waitFor();
        const id = await item.getAttribute('id');
        assert.match(id, /^[a-p]{32}$/);
        // Confirms background.js registered as the extension's service worker without
        // a fatal error preventing load (a broken script would leave this list empty).
        const worker = context.serviceWorkers().find(w => w.url() === `chrome-extension://${id}/background.js`)
            ?? await context.waitForEvent('serviceworker', w => w.url() === `chrome-extension://${id}/background.js`, { timeout: 5000 });
        assert.ok(worker);
        await page.goto(`chrome-extension://${id}/popup.html`);
        await page.waitForFunction(() => document.querySelector('#status').classList.contains('error'));
        assert.match(await page.locator('h1').textContent(), /Clip a public source/);
        // Opening an extension page is not a user grant for any web page.
        assert.equal(await page.locator('#source-url').isDisabled(), true);
        assert.match(await page.locator('#status').textContent(), /public article or social post/);
    } finally { await context.close(); }
});

test('handoff removes malformed clips and handles unavailable session storage', async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage();
        await page.route('https://www.u9itus.com/**', route => {
            const path = new URL(route.request().url()).pathname;
            return route.fulfill({ contentType: path.startsWith('/js/') ? 'text/javascript' : 'text/html', body: path.startsWith('/js/') ? read('public' + path) : '<p data-clip-handoff></p><script type="module" src="/js/chatter-clip-import.js"></script>' });
        });
        for (const hash of ['#clip=%broken', '#clip=' + 'a'.repeat(32001)]) {
            // Each extension action opens a new document, rather than changing an existing fragment.
            await page.goto('about:blank');
            await page.goto('https://www.u9itus.com/contribute/chatter/clip' + hash);
            await page.waitForFunction(() => document.querySelector('[data-clip-handoff]').textContent.includes('could not'));
            assert.equal(new URL(page.url()).hash, '');
            assert.equal(await page.evaluate(key => sessionStorage.getItem(key), key), null);
        }
        await page.addInitScript(() => { Storage.prototype.setItem = () => { throw new Error('Storage disabled'); }; });
        await page.goto('about:blank');
        await page.goto('https://www.u9itus.com/contribute/chatter/clip' + fragment);
        await page.waitForFunction(() => document.querySelector('[data-clip-handoff]').textContent.includes('could not'));
        assert.equal(new URL(page.url()).hash, '');
    } finally { await browser.close(); }
});
