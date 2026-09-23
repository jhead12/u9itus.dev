import React from 'react';

/**
 * List/endorsement blocks read from this module-level store instead of
 * their own Puck props, so a portal's candidate/measure/endorsement
 * content always reflects the organization's current target_state/
 * target_district (see App\Services\PortalDataService) rather than a
 * stale, manually-picked snapshot baked into the saved layout JSON.
 * setPortalData() is called once by app.js before the config is used.
 */
let portalData = { candidates: [], ballotMeasures: [], endorsements: [] };
let canEndorseCandidates = true;

export function setPortalData(data) {
    portalData = data || portalData;
}

export function setCanEndorseCandidates(value) {
    canEndorseCandidates = !!value;
}

function partyColor(party) {
    const p = (party || '').toLowerCase();
    if (p.startsWith('d')) return '#2563eb';
    if (p.startsWith('r')) return '#dc2626';
    return '#6b7280';
}

export const puckConfig = {
    root: {
        render: ({ children }) => <div className="portal-root">{children}</div>,
    },
    components: {
        Hero: {
            fields: {
                logoUrl: { type: 'text', label: 'Logo URL' },
                orgName: { type: 'text', label: 'Organization name' },
                bannerText: { type: 'textarea', label: 'Banner text' },
                primaryColor: { type: 'text', label: 'Primary color (hex)' },
            },
            defaultProps: {
                orgName: 'Your Organization',
                bannerText: 'Know before you vote.',
                primaryColor: '#4f46e5',
            },
            render: ({ logoUrl, orgName, bannerText, primaryColor }) => (
                <div className="portal-hero" style={{ background: primaryColor || '#4f46e5' }}>
                    {logoUrl ? <img src={logoUrl} alt={orgName} className="portal-hero-logo" /> : null}
                    <h1>{orgName}</h1>
                    <p>{bannerText}</p>
                </div>
            ),
        },
        CandidateList: {
            fields: {
                heading: { type: 'text', label: 'Heading' },
                limit: { type: 'number', label: 'Max candidates shown' },
            },
            defaultProps: { heading: 'Candidates', limit: 12 },
            render: ({ heading, limit }) => (
                <section className="portal-section">
                    <h2>{heading}</h2>
                    {portalData.candidates.length === 0 ? (
                        <p className="portal-empty">No candidates found for this portal's district yet.</p>
                    ) : (
                        <ul className="portal-candidate-list">
                            {portalData.candidates.slice(0, limit || 12).map((c) => (
                                <li key={c.id} className="portal-candidate-card">
                                    {c.profile_photo_url ? <img src={c.profile_photo_url} alt={c.full_name} /> : null}
                                    <div>
                                        <strong>{c.full_name}</strong>
                                        <span style={{ color: partyColor(c.party_affiliation) }}> {c.party_affiliation}</span>
                                        <div className="portal-candidate-office">
                                            {c.political_office}
                                            {c.district ? ` · District ${c.district}` : ''}
                                        </div>
                                        {c.slug ? (
                                            <a href={`/p/${c.slug}`} target="_blank" rel="noreferrer">View profile</a>
                                        ) : null}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            ),
        },
        BallotMeasureList: {
            fields: { heading: { type: 'text', label: 'Heading' } },
            defaultProps: { heading: 'Ballot Measures' },
            render: ({ heading }) => (
                <section className="portal-section">
                    <h2>{heading}</h2>
                    {portalData.ballotMeasures.length === 0 ? (
                        <p className="portal-empty">No ballot measures found for this portal's state yet.</p>
                    ) : (
                        portalData.ballotMeasures.map((m) => (
                            <div key={m.id} className="portal-measure-card">
                                <h3>{m.measure_number ? `${m.measure_number} — ${m.title}` : m.title}</h3>
                                <p>{m.summary}</p>
                                <div className="portal-measure-meanings">
                                    <div><strong>YES means:</strong> {m.yes_meaning}</div>
                                    <div><strong>NO means:</strong> {m.no_meaning}</div>
                                </div>
                            </div>
                        ))
                    )}
                </section>
            ),
        },
        EndorsementBadges: {
            fields: { heading: { type: 'text', label: 'Heading' } },
            defaultProps: { heading: 'Our Endorsements' },
            render: ({ heading }) => (
                <section className="portal-section">
                    <h2>{heading}</h2>
                    {portalData.endorsements.length === 0 ? (
                        <p className="portal-empty">
                            No endorsements published yet — add some from the Endorsements panel below the editor
                            {!canEndorseCandidates
                                ? ' (candidate endorsements are disabled for this organization type — ballot-measure positions only).'
                                : '.'}
                        </p>
                    ) : (
                        <ul className="portal-endorsement-list">
                            {portalData.endorsements.map((e) => (
                                <li key={e.id} className={`portal-endorsement-badge portal-endorsement-${e.position}`}>
                                    <strong>{e.label}</strong>
                                    <span>{e.politician ? e.politician.full_name : e.ballot_measure ? e.ballot_measure.title : ''}</span>
                                    {e.note ? <p>{e.note}</p> : null}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            ),
        },
        EmbedCta: {
            fields: {
                text: { type: 'text', label: 'Button text' },
                url: { type: 'text', label: 'Button URL' },
            },
            defaultProps: { text: 'Learn more', url: '#' },
            render: ({ text, url }) => (
                <div className="portal-cta">
                    <a href={url} className="portal-cta-button" target="_blank" rel="noreferrer">{text}</a>
                </div>
            ),
        },
    },
};
