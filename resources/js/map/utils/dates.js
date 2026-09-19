/**
 * Calendar-date helpers for the map.
 *
 * The API sends election, filing and birth dates as bare "YYYY-MM-DD" strings.
 * `new Date("2026-11-03")` reads those as UTC midnight, so toLocaleDateString()
 * in any US timezone prints the day before ("Nov 2") — while the server-formatted
 * election-date pills say "Nov 3". Parse the calendar parts as a local date so
 * every place on the map shows the same day.
 */

/** @returns {Date|null} local-midnight Date for the calendar day in `value`, or null if unparseable. */
export function parseCalendarDate(value) {
    if (!value) return null;
    if (value instanceof Date) return isNaN(value) ? null : value;
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value));
    if (m) return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
    const d = new Date(value);
    return isNaN(d) ? null : d;
}

const DEFAULT_FORMAT = { month: 'short', day: 'numeric', year: 'numeric' };

/** "Nov 3, 2026" (or whatever `options` asks for); '' when `value` isn't a date. */
export function formatCalendarDate(value, options = DEFAULT_FORMAT) {
    const d = parseCalendarDate(value);
    return d ? d.toLocaleDateString('en-US', options) : '';
}

/** True once the calendar day in `value` is strictly before today. */
export function isBeforeToday(value) {
    const d = parseCalendarDate(value);
    if (!d) return false;
    const now = new Date();
    return d < new Date(now.getFullYear(), now.getMonth(), now.getDate());
}
