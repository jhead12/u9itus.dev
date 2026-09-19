/** Keep race outcomes separate from whether an official is currently serving. */
export function parseElectionStatus(statusText) {
  const text = String(statusText ?? '').toLowerCase().trim();
  const election_stage = /\bprimary\b/.test(text) ? 'primary'
    : /\bgeneral\b/.test(text) ? 'general'
      : /\bspecial\b/.test(text) ? 'special' : null;
  let result_status = null;
  if (/\blost\b|\bdefeated\b|\beliminated\b/.test(text)) result_status = 'lost';
  else if (/\badvanced?\b|\badvances\b/.test(text)
    || (election_stage === 'primary' && /\bwon\b|\bwinner\b/.test(text))) result_status = 'advanced_to_general';
  else if (/\bwon\b|\belected\b|\bwinner\b/.test(text)) result_status = 'won';
  return { result_status, election_stage, is_running_candidate: result_status === null || result_status === 'advanced_to_general' };
}

/** Scheduled general election, never a claim about a primary or special date. */
export function generalElectionDate(year) {
  if (!Number.isInteger(year) || year < 2000 || year > 2100 || year % 2 !== 0) return null;
  const first = new Date(Date.UTC(year, 10, 1));
  const firstMonday = 1 + (8 - first.getUTCDay()) % 7;
  return `${year}-11-${String(firstMonday + 1).padStart(2, '0')}`;
}
