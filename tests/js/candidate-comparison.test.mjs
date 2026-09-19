import test from 'node:test';
import assert from 'node:assert/strict';
import { renderComparison, initialComparisonSelection } from '../../resources/js/map/ui/candidate-comparison.js';
const fixture = () => ({
    seat: { label: 'CA-03' }, selected_key: 'b', election: { date: '2026-11-03', stage: 'General' },
    candidates: ['a', 'b', 'c', 'd'].map(key => ({ key, full_name: key, party: 'Independent', stances: [] })),
});
test('starts with the selected candidate and limits comparison to three columns', () => {
    const data = fixture();
    assert.deepEqual(initialComparisonSelection(data), ['b', 'a']);
    const html = renderComparison(data, ['a', 'b', 'c']);
    assert.match(html, /data-compare-key="d" disabled/);
    assert.match(html, /2026-11-03/);
    assert.match(html, /Not recorded/);
});
test('escapes candidate statements and rejects unsafe source URLs', () => {
    const data = fixture();
    data.candidates[0].stances = [{ topic: '<img onerror=alert(1)>', text: '<script>bad()</script>', source_url: 'javascript:alert(1)', source_label: 'source' }];
    const html = renderComparison(data, ['a']);
    assert.ok(!html.includes('<script>'));
    assert.ok(!html.includes('href="javascript:'));
    assert.match(html, /&lt;img/);
});
test('date-only source stamps preserve their calendar day in western time zones', () => {
    const previous = process.env.TZ;
    process.env.TZ = 'America/Los_Angeles';
    try {
        const data = fixture();
        data.candidates[0].updated_at = '2026-09-19';
        assert.match(renderComparison(data, ['a']), /Updated Sep 19, 2026/);
    } finally {
        if (previous === undefined) delete process.env.TZ;
        else process.env.TZ = previous;
    }
});
