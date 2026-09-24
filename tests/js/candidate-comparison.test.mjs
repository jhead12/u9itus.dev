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

test('research view aligns topic headings and keeps missing positions explicit', () => {
    const data = fixture();
    data.election = null;
    data.candidates[0].stances = [{ topic: 'Housing', text: 'Build homes', source_url: 'https://example.com/a' }];
    data.candidates[1].stances = [{ topic: ' housing ', text: 'Repair homes', source_url: 'https://example.com/b' }];
    const html = renderComparison(data, ['a', 'b', 'c'], { basic: true });
    assert.equal((html.match(/<th scope="row">Housing<\/th>/g) || []).length, 1);
    assert.match(html, /No upcoming election is confirmed/);
    assert.match(html, /Not recorded/);
    assert.ok(html.includes('<th scope="row">Campaign finance</th>'));
});
test('comparison table shows recent coverage and press releases separately, with safe links and no tone', () => {
    const data = { seat: { label: 'U.S. House · CA-03' }, candidates: [
        { key: 'a', full_name: 'Alex Rivera', news: { coverage: [{ headline: 'Rivera <b>profile</b>', source_name: 'Daily News', source_url: 'javascript:alert(1)', published_at: '2026-09-01' }], press_releases: [] } },
        { key: 'b', full_name: 'Jamie Carter', news: { coverage: [], press_releases: [{ headline: 'Carter statement', source_name: 'Carter campaign', source_url: 'https://carter.example.com/p', published_at: '2026-09-10' }] } },
    ] };
    const html = renderComparison(data, ['a', 'b'], { basic: true });
    assert.match(html, /Recent news coverage/);
    assert.match(html, /Candidate press releases/);
    assert.match(html, /Rivera &lt;b&gt;profile&lt;\/b&gt;/);
    assert.doesNotMatch(html, /javascript:/);
    assert.match(html, /href="https:\/\/carter\.example\.com\/p"/);
    assert.match(html, /None recorded in the last year/);
    assert.match(html, /do not rate coverage as positive or negative/);
    assert.doesNotMatch(renderComparison({ ...data, candidates: data.candidates.map(({ news, ...c }) => c) }, ['a', 'b']), /Recent news coverage/);
});
test('the standalone page shows campaign finance, record, and issue focus like the map', () => {
    const data = { seat: { label: 'U.S. House · CA-03' }, candidates: [{ key: 'a', full_name: 'Alex Rivera', issue_focus: ['Housing'],
        finance: { cycle: 2026, receipts: '$10', source_url: 'https://www.fec.gov/data/' }, legislation: { sponsored: 3, cosponsored: 5 } }] };
    const html = renderComparison(data, ['a'], { basic: true });
    assert.match(html, /Campaign finance/);
    assert.match(html, /Legislative record/);
    assert.match(html, /Issue focus/);
});
