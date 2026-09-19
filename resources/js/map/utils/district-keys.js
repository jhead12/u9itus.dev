/**
 * Shared district-key helpers. `house_candidates` keys arrive as "CA-33" or
 * "CA-3" depending on which import wrote the row, and at-large districts are
 * "AK-AL" — every consumer needs the same lookup, so it lives here.
 */

/** "CA" + "3" → "CA-03"; "AK" + "AL" → "AK-AL". */
export function districtCode(abbr, num) {
    return num === 'AL' ? `${abbr}-AL` : `${abbr}-${String(num).padStart(2, '0')}`;
}

/** Candidates for one district from a state-candidates payload, tolerating both key styles. */
export function houseCandidatesFor(data, abbr, num) {
    const map = data?.house_candidates;
    if (!map) return [];
    if (num === 'AL') return map[`${abbr}-AL`] ?? [];
    return map[districtCode(abbr, num)] ?? map[`${abbr}-${num}`] ?? [];
}

/** The sitting member, if the list has one. */
export function seatedMember(candidates) {
    return (candidates ?? []).find(c => c.status === 'seated' || (c.status === 'active' && !c.is_running)) ?? null;
}
