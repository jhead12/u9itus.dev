import { escapeComparisonText as esc } from '../map/ui/candidate-comparison.js';

const topicKey = value => String(value ?? '').trim().replace(/\s+/g, ' ').toLowerCase();

export function guideDate(value) {
    if (!value) return null;
    const date = new Date(/^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T12:00:00` : value);
    return Number.isNaN(date.getTime()) ? null : date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
}

/**
 * "What does this office do?" copy. Offices differ by state and city, so a general
 * description says so; a sourced state or city note names its source.
 */
export function roleText(role) {
    if (!role) return null;
    const place = role.place ? ` in ${role.place}` : '';
    let safeSource = null;
    try { const url = new URL(role.source_url); if (['http:', 'https:'].includes(url.protocol)) safeSource = url.href; } catch { /* no source link */ }
    if (role.scope !== 'general' && role.source_label) {
        return { heading: `${role.title}${place}`, description: role.description,
            note: `Source: ${role.source_label}${role.reviewed_at ? ` · Reviewed ${guideDate(role.reviewed_at) || role.reviewed_at}` : ''}`, sourceUrl: safeSource };
    }
    return { heading: role.title, description: role.description,
        note: `General description. The powers of this office${place} may be different. Check your state or local election office's official voter guide.`, sourceUrl: null };
}

/**
 * Save-as-PDF file name (browsers use the page title): the compared people, the
 * seat, and the date the guide was prepared, e.g.
 * "Malia Cohen vs Steve Hilton - State Controller CA - 2026-09-24 - U9itus guide".
 */
export function guideFileName(data, selectedKeys, date = new Date()) {
    const names = (data?.candidates ?? []).filter(c => selectedKeys.includes(c.key)).slice(0, 3).map(c => c.full_name);
    const day = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    const seat = String(data?.seat?.label ?? '').replace(/\s*·\s*/g, ' ');
    const name = [names.join(' vs '), seat, day, 'U9itus guide'].filter(Boolean).join(' - ');
    // Characters most file systems reject become hyphens; keep it a sensible length.
    return name.replace(/[\\/:*?"<>|]+/g, '-').replace(/\s+/g, ' ').trim().slice(0, 180);
}

/** Print-specific presentation: source text is preserved without summarizing or rating it. */
export function renderPrintGuide(data, selectedKeys) {
    const candidates = (data?.candidates ?? []).filter(c => selectedKeys.includes(c.key)).slice(0, 3);
    if (!candidates.length) return { html: '', sources: [] };
    const sources = [];
    const sourceNumbers = new Map();
    function reference(url, label, updatedAt, dateWord = 'Updated') {
        let number = null;
        try {
            const parsed = new URL(url);
            if (['http:', 'https:'].includes(parsed.protocol)) {
                if (!sourceNumbers.has(parsed.href)) {
                    sources.push({ url: parsed.href, label: label || 'Source' });
                    sourceNumbers.set(parsed.href, sources.length);
                }
                number = sourceNumbers.get(parsed.href);
            }
        } catch { /* A label without a valid source URL is not made into a link. */ }
        const updated = guideDate(updatedAt);
        return `<p class="guide-reference">${esc(label || 'Source')}${number ? ` [${number}]` : ' (link not recorded)'}<br>${updated ? `${dateWord} ${esc(updated)}` : `${dateWord === 'Updated' ? 'Update' : dateWord} date not recorded`}</p>`;
    }
    const headings = new Map();
    for (const candidate of candidates) {
        for (const stance of candidate.stances ?? []) {
            const key = topicKey(stance.topic);
            if (key && !headings.has(key)) headings.set(key, stance.topic.trim());
        }
    }
    const topics = [...headings].sort((a, b) => a[1].localeCompare(b[1]));
    const seat = esc(data.seat?.label || 'Selected seat');
    const role = roleText(data.seat?.role);
    const header = '<p class="guide-brand">U9itus · Independent voter research</p>';
    const table = (caption, rows) => `<table class="guide-table"><caption>${esc(caption)}</caption><thead><tr><th scope="col">Information</th>${candidates.map(c => `<th scope="col">${esc(c.full_name)}</th>`).join('')}</tr></thead><tbody>${rows}</tbody></table>`;
    const row = (label, render) => `<tr><th scope="row">${esc(label)}</th>${candidates.map(c => `<td>${render(c)}</td>`).join('')}</tr>`;
    const field = key => c => esc(c[key] || 'Not recorded');
    const profileRows = row('Political party', field('party')) + row('Current role', field('incumbency'))
        + row('Running status', field('candidacy'))
        + row('Record source', c => reference(c.profile_url, c.source_label, c.updated_at));
    const electionDate = guideDate(data.election?.date);
    const missing = selectedKeys.filter(key => !candidates.some(c => c.key === key));
    const issueTitles = topics.map(([, title]) => `What have they said about ${title}?`);
    const hasNews = candidates.some(c => c.news);
    const newsTitle = 'What is the latest news?';
    const contents = ['Who is being compared?', ...issueTitles, ...(hasNews ? [newsTitle] : []), 'Where can I check the sources?'];
    const intro = `<section class="guide-section guide-overview">
        ${header}<h1>Compare candidates</h1><p class="guide-seat">${seat}</p>
        <p class="guide-election">${electionDate ? `Recorded election: ${esc(data.election.stage || 'Election')} · ${esc(electionDate)}` : 'Election date not confirmed'}</p>
        <p data-guide-generated></p>
        ${role ? `<h2>What does this office do?</h2><p><strong>${esc(role.heading)}.</strong> ${esc(role.description)}</p><p>${esc(role.note)}${role.sourceUrl ? ` ${esc(role.sourceUrl)}` : ''}</p><p>Glossary of offices: ${esc(data.seat.role.glossary_url)}</p>` : ''}
        <h2>How do I use this guide?</h2>
        <p>Compare the same information across each column. Read the numbered sources to check the evidence. “Not recorded” means information is missing; it does not mean support or opposition.</p>
        <p>This guide includes ${candidates.length} selected ${candidates.length === 1 ? 'person' : 'people'}. It may not include everyone on your ballot. A current officeholder may not be running.</p>
        ${missing.length ? `<p class="guide-notice">Selected records unavailable: ${missing.map(esc).join(', ')}. No replacements were selected.</p>` : ''}
        ${data.message ? `<p class="guide-notice">${esc(data.message)}</p>` : ''}
        <h2>What is in this guide?</h2><ol class="guide-contents">${contents.map(title => `<li>${esc(title)}</li>`).join('')}</ol>
    </section>
    <section class="guide-section">${header}<p class="guide-seat">${seat}</p><h2>1. Who is being compared?</h2>
        ${table('Party, current role, and running status', profileRows)}
        ${topics.length ? '' : '<p>No policy statements are recorded for these selected people.</p>'}
    </section>`;
    const issueSections = topics.map(([key], i) => {
        const rows = row('Recorded statements', candidate => {
            const positions = (candidate.stances ?? []).filter(s => topicKey(s.topic) === key);
            return positions.length ? positions.map(s => `<div class="guide-statement"><p>${esc(s.text)}</p>${s.quote ? `<blockquote>“${esc(s.quote)}”</blockquote>` : ''}${reference(s.source_url, s.source_label || 'Source', s.updated_at)}</div>`).join('') : '<p>Not recorded</p>';
        });
        return `<section class="guide-section">${header}<p class="guide-seat">${seat}</p><h2>${i + 2}. ${esc(issueTitles[i])}</h2>${table(issueTitles[i], rows)}</section>`;
    }).join('');
    const articleRow = (label, group) => row(label, candidate => {
        const list = candidate.news?.[group] ?? [];
        return list.length ? list.map(a => `<div class="guide-statement"><p>${esc(a.headline)}</p>${reference(a.source_url, a.source_name || 'Source', a.published_at, 'Published')}</div>`).join('')
            : '<p>None recorded in the last year</p>';
    });
    const newsSection = hasNews ? `<section class="guide-section">${header}<p class="guide-seat">${seat}</p><h2>${topics.length + 2}. ${newsTitle}</h2>
        <p>News coverage is reporting about a candidate. It is not the candidate's position. Press releases are written by the candidate or their office. We list recent articles as found and do not rate them as positive or negative.</p>
        ${table('Recent news coverage and press releases', articleRow('News coverage', 'coverage') + articleRow('Candidate press releases', 'press_releases'))}</section>` : '';
    return { html: intro + issueSections + newsSection, sources, sourceSection: topics.length + (hasNews ? 3 : 2) };
}
