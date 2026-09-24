import test from 'node:test';
import assert from 'node:assert/strict';
import { renderPrintGuide, guideDate, guideFileName, roleText } from '../../resources/js/compare/print-guide.js';

const fixture = () => ({ seat: { label: 'U.S. House · CA-03' }, election: { stage: 'General', date: '2026-11-03' }, candidates: [
    { key: 'a', full_name: 'Alex Rivera', party: 'Independent', stances: [{ topic: 'Housing', text: 'Build homes.', source_url: 'https://example.com/policy', source_label: 'Platform', updated_at: '2026-09-19' }] },
    { key: 'b', full_name: 'Jamie Carter', stances: [{ topic: ' housing ', text: 'Repair homes.', source_url: 'https://example.com/policy', source_label: 'Platform' }] },
] });
test('print guide has question headings, explicit dates, aligned issues and deduplicated references', () => {
    const guide = renderPrintGuide(fixture(), ['a', 'b']);
    assert.match(guide.html, /November 3, 2026/);
    assert.match(guide.html, /How do I use this guide\?/);
    assert.match(guide.html, /2\. What have they said about Housing\?/);
    assert.match(guide.html, /Build homes\./);
    assert.match(guide.html, /Repair homes\./);
    assert.match(guide.html, /Not recorded/);
    assert.match(guide.html, /Independent voter research/);
    assert.equal(guide.sourceSection, 3);
    assert.equal(guide.sources.length, 1);
});
test('print guide escapes evidence and never substitutes missing selections', () => {
    const data = fixture();
    data.candidates[0].stances[0].text = '<script>bad()</script>';
    data.candidates[0].stances[0].source_url = 'javascript:alert(1)';
    const guide = renderPrintGuide(data, ['a', 'missing']);
    assert.ok(!guide.html.includes('<script>'));
    assert.ok(!guide.html.includes('Jamie Carter'));
    assert.match(guide.html, /Selected records unavailable: missing/);
    assert.equal(guide.sources.length, 0);
});
test('print date-only values retain their day in western time zones', () => {
    const previous = process.env.TZ;
    process.env.TZ = 'America/Los_Angeles';
    try { assert.equal(guideDate('2026-11-03'), 'November 3, 2026'); }
    finally { if (previous === undefined) delete process.env.TZ; else process.env.TZ = previous; }
});
test('print guide labels a general office description as possibly different for the place', () => {
    const data = fixture();
    data.seat.role = { title: 'Mayor', description: 'Leads <city> government.', scope: 'general', place: 'Oakland, CA', glossary_url: 'https://u9itus.test/compare/glossary#mayor' };
    const guide = renderPrintGuide(data, ['a']);
    assert.match(guide.html, /What does this office do\?/);
    assert.match(guide.html, /Leads &lt;city&gt; government\./);
    assert.match(guide.html, /General description\. The powers of this office in Oakland, CA may be different\./);
    assert.match(guide.html, /Glossary of offices: https:\/\/u9itus\.test\/compare\/glossary#mayor/);
    assert.doesNotMatch(renderPrintGuide(fixture(), ['a']).html, /What does this office do\?/);
});
test('a sourced place note names its place, source, and review date, and drops unsafe links', () => {
    const role = { title: 'Mayor', description: 'Runs city agencies.', scope: 'city', place: 'New York, NY', source_label: 'New York City Charter', source_url: 'https://example.gov/charter', reviewed_at: '2026-09-24' };
    const text = roleText(role);
    assert.equal(text.heading, 'Mayor in New York, NY');
    assert.match(text.note, /^Source: New York City Charter · Reviewed September 24, 2026$/);
    assert.equal(text.sourceUrl, 'https://example.gov/charter');
    assert.equal(roleText({ ...role, source_url: 'javascript:alert(1)' }).sourceUrl, null);
});
test('saved PDF name lists the selected people, seat, and preparation date without unsafe characters', () => {
    const data = fixture();
    data.candidates[1].full_name = 'Jamie "JC" Carter/Smith';
    assert.equal(guideFileName(data, ['a', 'b'], new Date(2026, 8, 24, 3, 53)),
        'Alex Rivera vs Jamie -JC- Carter-Smith - U.S. House CA-03 - 2026-09-24 - U9itus guide');
    assert.equal(guideFileName(data, ['b'], new Date(2026, 0, 5)).startsWith('Jamie -JC- Carter-Smith - U.S. House CA-03 - 2026-01-05'), true);
});
test('print guide lists news and press releases in their own section with numbered sources and no tone', () => {
    const data = fixture();
    data.candidates[0].news = { coverage: [{ headline: 'Rivera <wins> endorsement', source_name: 'Daily News', source_url: 'https://news.example.com/a', published_at: '2026-09-01' }],
        press_releases: [{ headline: 'Rivera statement', source_name: 'Rivera campaign', source_url: 'https://rivera.example.com/p', published_at: '2026-09-10' }] };
    data.candidates[1].news = { coverage: [], press_releases: [] };
    const guide = renderPrintGuide(data, ['a', 'b']);
    assert.match(guide.html, /3\. What is the latest news\?/);
    assert.match(guide.html, /Rivera &lt;wins&gt; endorsement/);
    assert.match(guide.html, /Daily News \[2\]<br>Published September 1, 2026/);
    assert.match(guide.html, /None recorded in the last year/);
    assert.match(guide.html, /do not rate them as positive or negative/);
    assert.equal(guide.sourceSection, 4);
    assert.equal(guide.sources.length, 3);
});
test('print guide shows FEC money with its cycle, period, and numbered source', () => {
    const data = fixture();
    data.candidates[0].finance = { cycle: 2026, receipts: '$1,200,000', disbursements: '$800,000', cash_on_hand: '$400,000', coverage_end_date: '2026-06-30', source_url: 'https://www.fec.gov/data/candidate/H0CA03000/' };
    const guide = renderPrintGuide(data, ['a', 'b']);
    assert.match(guide.html, /Campaign money/);
    assert.match(guide.html, /Raised: \$1,200,000<br>Spent: \$800,000<br>Cash on hand: \$400,000/);
    assert.match(guide.html, /FEC filings, 2026 cycle \[\d\]<br>Through June 30, 2026/);
    assert.ok(guide.sources.some(s => s.url.startsWith('https://www.fec.gov/')));
});
test('print guide says state campaign money is not collected rather than implying none was raised', () => {
    const data = fixture();
    data.seat.finance_note = "State and local campaign finance isn't collected yet. FEC data covers federal races only.";
    assert.match(renderPrintGuide(data, ['a']).html, /State and local campaign finance isn&#39;t collected yet/);
});
