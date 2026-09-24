/** Presentation only: never infer a stance from a party, donation, or issue-focus badge. */
export const escapeComparisonText = value => String(value ?? '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
const esc = escapeComparisonText;

function sourceLink(url, label) {
    try {
        const parsed = new URL(url);
        if (!['http:', 'https:'].includes(parsed.protocol)) return esc(label);
        return `<a href="${esc(parsed.href)}" target="_blank" rel="noopener noreferrer">${esc(label)}</a>`;
    } catch { return esc(label); }
}
function dateLabel(value) {
    if (!value) return 'Update date not recorded';
    const date = new Date(/^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T12:00:00` : value);
    return Number.isNaN(date.getTime()) ? 'Update date not recorded'
        : `Updated ${date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
}
const topicKey = title => String(title ?? '').trim().replace(/\s+/g, ' ').toLowerCase();

export function initialComparisonSelection(data) {
    const candidates = data?.candidates ?? [];
    const anchor = candidates.find(c => c.key === data.selected_key) ?? candidates[0];
    return [anchor?.key, candidates.find(c => c.key !== anchor?.key)?.key].filter(Boolean);
}

export function renderComparison(data, selectedKeys = [], options = {}) {
    const candidates = data?.candidates ?? [];
    if (!candidates.length) {
        return `<section class="pol-compare"><h3>Compare this seat</h3><p class="compare-note">${esc(data?.message || 'No comparison data is available for this seat yet.')}</p></section>`;
    }
    const selected = candidates.filter(c => selectedKeys.includes(c.key)).slice(0, 3);
    const titles = new Map();
    for (const candidate of selected) {
        for (const stance of candidate.stances ?? []) {
            const key = topicKey(stance.topic);
            if (key && !titles.has(key)) titles.set(key, stance.topic);
        }
    }
    const row = (label, cells) => `<tr><th scope="row">${esc(label)}</th>${cells.map(text => `<td>${text}</td>`).join('')}</tr>`;
    const empty = '<span class="compare-missing">Not recorded</span>';
    const stanceRows = [...titles].sort((a, b) => a[1].localeCompare(b[1])).map(([key, title]) => row(title, selected.map(candidate => {
        const positions = (candidate.stances ?? []).filter(s => topicKey(s.topic) === key);
        return positions.length ? positions.map(s => `<div class="compare-stance"><p>${esc(s.text)}</p>${s.quote ? `<blockquote>“${esc(s.quote)}”</blockquote>` : ''}<small>${sourceLink(s.source_url, s.source_label || 'Source')}<br>${esc(dateLabel(s.updated_at))}</small></div>`).join('') : empty;
    }))).join('');
    const finance = c => {
        const f = c.finance;
        if (!f) return empty;
        const lines = [['Raised', f.receipts], ['Spent', f.disbursements], ['Cash on hand', f.cash_on_hand]]
            .filter(([, v]) => v).map(([k, v]) => `<div class="compare-figure"><span>${esc(k)}</span> <strong>${esc(v)}</strong></div>`).join('');
        const through = f.coverage_end_date ? ` · through ${esc(dateLabel(f.coverage_end_date).replace('Updated ', ''))}` : '';
        return `${lines}<small>${f.cycle ? `${esc(f.cycle)} cycle${through} · ` : ''}${sourceLink(f.source_url, 'FEC filings')}</small>`;
    };
    const legislation = c => {
        const l = c.legislation;
        if (!l) return empty;
        const bills = l.sponsored != null
            ? `<div class="compare-figure"><strong>${esc(l.sponsored)}</strong> <span>bills sponsored</span> · <strong>${esc(l.cosponsored)}</strong> <span>cosponsored</span></div>` : '';
        const committees = (l.committees ?? []).length
            ? `<ul class="compare-list">${l.committees.slice(0, 4).map(n => `<li>${esc(n)}</li>`).join('')}${l.committees.length > 4 ? `<li>+${l.committees.length - 4} more</li>` : ''}</ul>` : '';
        return `${bills}${committees}${l.since_congress ? `<small>Since the ${esc(l.since_congress)}th Congress · Congress.gov</small>` : ''}` || empty;
    };
    // Reporting and press releases are listed as found, newest first; tone is never rated.
    const hasNews = selected.some(c => c.news);
    const articles = list => (list ?? []).length
        ? `<ul class="compare-news">${list.map(a => `<li>${sourceLink(a.source_url, a.headline)}<small>${esc([a.source_name, dateLabel(a.published_at).replace('Updated ', '')].filter(Boolean).join(' · '))}</small></li>`).join('')}</ul>`
        : '<span class="compare-missing">None recorded in the last year</span>';
    const focus = c => (c.issue_focus ?? []).length
        ? `<ul class="compare-chips">${c.issue_focus.map(t => `<li>${esc(t)}</li>`).join('')}</ul>` : empty;
    return `<section class="pol-compare">
        <p class="compare-eyebrow">ONE SEAT · SIDE BY SIDE</p>
        <h3>${esc(data.seat?.label || 'Compare candidates')}</h3>
        ${data.election?.date ? `<p class="compare-notice">${esc(data.election.stage)} · ${esc(data.election.date)}</p>` : options.basic ? '<p class="compare-notice">No upcoming election is confirmed for this seat.</p>' : ''}
        <p class="compare-note">Choose up to three people. A current officeholder may not be running in the next election.</p>
        ${data.message ? `<p class="compare-notice" role="status">${esc(data.message)}</p>` : ''}
        <fieldset class="compare-picker"><legend>Candidates <span>(${selected.length}/3 selected)</span></legend>
            ${candidates.map(c => `<label><input type="checkbox" data-compare-key="${esc(c.key)}" ${selectedKeys.includes(c.key) ? 'checked' : selectedKeys.length >= 3 ? 'disabled' : ''}><span>${esc(c.full_name)}</span></label>`).join('')}
        </fieldset>
        ${selected.length ? `<p class="compare-scroll-hint">Scroll sideways to see every column.</p>
        <div class="compare-table-wrap" tabindex="0" role="region" aria-label="Side-by-side candidate comparison">
            <table class="compare-table" style="min-width:${112 + selected.length * 205}px"><caption>Party, incumbency, campaign finance, record, and recorded policy positions for ${esc(data.seat?.label)}</caption>
            <thead><tr><th scope="col">Compare</th>${selected.map(c => `<th scope="col">${esc(c.full_name)}</th>`).join('')}</tr></thead>
            <tbody>
                ${row('Party', selected.map(c => esc(c.party || 'Not recorded')))}
                ${row('Incumbency', selected.map(c => esc(c.incumbency || 'Not recorded')))}
                ${row('Candidacy', selected.map(c => esc(c.candidacy || 'Not recorded')))}
                ${row('Campaign finance', selected.map(finance))}
                ${selected.some(c => c.legislation) ? row('Legislative record', selected.map(legislation)) : ''}
                ${row('Issue focus', selected.map(focus))}
                ${stanceRows || row('Policy positions', selected.map(() => empty))}
                ${hasNews ? row('Recent news coverage', selected.map(c => articles(c.news?.coverage))) : ''}
                ${hasNews ? row('Candidate press releases', selected.map(c => articles(c.news?.press_releases))) : ''}
                ${row('Record source', selected.map(c => `${sourceLink(c.profile_url, c.source_label || 'Public records')}<br><small>${esc(dateLabel(c.updated_at))}</small>`))}
            </tbody></table>
        </div>` : '<p class="compare-notice" role="status">Select a candidate above to start comparing.</p>'}
        <p class="compare-note compare-footnote">Positions are published statements, not ratings. Matching topic headings are aligned; missing information does not imply support or opposition. Issue focus lists the topics someone works on most, from bills, floor speeches, and news coverage; it is not a position. Party, money, and issue focus are never used to infer a stance.${hasNews ? ' News coverage is reporting about a candidate, not their position; press releases are written by the candidate or their office. We do not rate coverage as positive or negative.' : ''}</p>
    </section>`;
}

/** A public deep link using only the resolved seat and explicit selections. */
export function comparisonPageUrl(data, selectedKeys = []) {
    if (!data?.seat?.state) return null;
    const params = new URLSearchParams();
    for (const key of ['state', 'office', 'district', 'city']) {
        if (data.seat[key]) params.set(key, data.seat[key]);
    }
    // Statewide and local seats currently require an anchor name.
    if (!data.seat.district) params.set('full_name', data.candidates?.[0]?.full_name || data.anchor_name || '');
    if (selectedKeys.length) params.set('selected', selectedKeys.join(','));
    return `/compare?${params}`;
}
