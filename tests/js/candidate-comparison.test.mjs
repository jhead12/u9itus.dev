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
test('shows finance, record, and issue focus without treating focus as a position', () => {
    const data = fixture();
    Object.assign(data.candidates[0], {
        finance: { cycle: 2026, receipts: '$1,200,000', disbursements: '$800,000', cash_on_hand: '$400,000', source_url: 'https://www.fec.gov/data/' },
        legislation: { sponsored: 12, cosponsored: 90, since_congress: 118, committees: ['Financial Services'] },
        issue_focus: ['Housing'],
        stances: [{ topic: 'Housing', text: 'Supports more homes.', quote: 'We need more homes.', source_url: 'https://www.govinfo.gov/g1.htm', source_label: 'Congressional Record floor speech' }],
    });
    const html = renderComparison(data, ['a', 'b']);
    assert.match(html, /Raised<\/span> <strong>\$1,200,000/);
    assert.match(html, /Legislative record/);
    assert.match(html, /Financial Services/);
    assert.match(html, /<li>Housing<\/li>/);
    assert.match(html, /“We need more homes\.”/);
    assert.match(html, /it is not a position/);
    const plain = renderComparison(fixture(), ['a']);
    assert.ok(!plain.includes('Legislative record'));
});
