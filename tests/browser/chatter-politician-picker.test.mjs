import { test } from 'node:test';
import assert from 'node:assert/strict';
import { chromium } from 'playwright';

test('candidate picker filters, preserves selection, supports keyboard and isolates forms', async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage();
        const form = `<form><div data-politician-picker>
            <label hidden data-politician-search-label><input type="search" data-politician-search></label>
            <select name="politician_id" required data-politician-results>
                <option value="">Select a politician</option>
                <option value="1">Byron Donalds — FL · House · Republican</option>
                <option value="2">Susan Collins — ME · Senate · Republican</option>
                <option value="3">José Example — CA · House · Democrat</option>
            </select><p data-politician-status role="status"></p>
        </div></form>`;
        await page.setContent(form + form);
        await page.addScriptTag({ path: 'public/js/chatter-politician-picker.js' });
        const search = page.locator('[data-politician-search]').first();
        const results = page.locator('select').first();
        await search.fill('donald FL');
        assert.equal(await results.locator('option').count(), 2);
        await search.press('ArrowDown');
        assert.equal(await results.evaluate(element => element === document.activeElement), true);
        await results.selectOption('1');
        await search.fill('Senate ME');
        assert.equal(await results.inputValue(), '1');
        assert.match(await results.textContent(), /Susan Collins/);
        assert.match(await results.textContent(), /Byron Donalds.*selected/);
        await results.selectOption('2');
        assert.equal(await results.inputValue(), '2');
        await search.fill('not-a-candidate');
        assert.match(await page.locator('[role=status]').first().textContent(), /No matching candidates/);
        assert.equal(await results.inputValue(), '2');
        await search.fill('jose');
        assert.match(await results.textContent(), /José Example/);
        assert.equal(await page.locator('select').nth(1).locator('option').count(), 4);
        assert.equal(await page.locator('select').nth(1).inputValue(), '');
        await search.press('Escape');
        assert.equal(await search.inputValue(), '');
        assert.equal(await results.locator('option').count(), 4);
        await page.locator('form').first().evaluate(form => form.reset());
        await page.waitForFunction(() => document.querySelector('select').value === '');
        assert.equal(await results.evaluate(element => element.checkValidity()), false);
    } finally {
        await browser.close();
    }
});
