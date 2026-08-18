# Gubernatorial Candidate-Count Verification & RSS Candidate Discovery

## Purpose

Two related, non-scraping ways to catch missing candidates in
`politicians`/`election_candidate_records`:

1. **Count verification** — cross-check our declared-candidate count per
   gubernatorial race against a hand-checked Ballotpedia number, and flag
   states where we're short.
2. **RSS discovery** — proactively surface new Senate/Governor/House
   candidate signals from Google News before they'd otherwise be noticed.

Both exist because buying Ballotpedia's political data product (or scraping
it directly) wasn't worth it: the root cause of missing candidates has
consistently been pipeline gaps (a 6-hour job budget silently truncating
House imports, a desynced `is_running_candidate` column), not bad source
data. These two mechanisms are cheap, low-maintenance ways to catch that
class of gap going forward without taking on Ballotpedia's ToS/cost
tradeoffs.

## Why governor gets a count table and House/Senate don't

Gubernatorial races are a small, slow-changing set — about 36 per cycle,
with declared candidates rarely changing after the filing deadline. A human
skimming Ballotpedia's race page a few times a cycle is realistic upkeep.

House (435 districts) and, to a lesser extent, Senate churn is higher, and
435 hand-checked numbers isn't "occasional upkeep" — it's the same
data-purchase problem restated as manual labor. A **stale** reference count
is worse than no check at all, since it flags real, correct data as broken.
So House/Senate get the RSS discovery mechanism instead (see below), which
degrades gracefully — a missed signal is just a missed signal, not a false
alarm.

## Part 1: Governor count verification

### Seeding status (as of 2026-08-18)

31 of 36 states are seeded (both local and production), sourced from
Ballotpedia via two research passes — general-election nominee counts for
states whose primary already resolved, pre-primary filed-candidate counts
for states whose primary hadn't happened yet. 5 states are deliberately
**not** seeded — their available numbers were judged too likely to be
wrong or stale to trust:

- **AK, FL, WY** — primaries were held the same day this was seeded
  (2026-08-18); the only available number was the pre-primary filed-candidate
  field, about to go stale as soon as results are certified.
- **HI, TN** — no reliable count found across two research passes; sources
  were contradictory or incomplete on the full candidate field.

Running the audit immediately after seeding surfaced a real, sizeable gap:
30 of 31 seeded states show 0 or near-0 tracked candidates with
`is_running_candidate = true` (CA is the one exception, off by +1 — an
over-count worth its own investigation, since a re-sync can't fix that
direction). This is the intended failure mode working as designed, not a
bug in the seeding.

Follow-up, whenever convenient: recheck AK/FL/WY once their primary results
are certified, and take another pass at HI/TN's full candidate field.

### Schema

`governor_race_candidate_counts` (migration
`2026_08_18_000001_create_governor_race_candidate_counts_table.php`,
model `App\Models\GovernorRaceCandidateCount`):

| column          | meaning                                              |
|-----------------|-------------------------------------------------------|
| `state`         | two-letter code                                        |
| `election_year` | e.g. `2026`                                             |
| `expected_count`| declared-candidate count, from Ballotpedia's race page |
| `source`        | free-text, default `ballotpedia_manual`                |
| `source_url`    | the specific Ballotpedia race page, for reverification |
| `verified_at`   | when a human last checked it                            |

Unique on `(state, election_year)`.

### Maintaining the numbers

```
php artisan election:set-race-count CA --count=4 \
  --url=https://ballotpedia.org/California_gubernatorial_election,_2026
```

Re-run whenever you've rechecked a race (the filing deadline is the most
useful checkpoint — counts rarely move after that). This is a manual step
by design; nothing in the pipeline scrapes Ballotpedia.

### Running the audit

```
php artisan politicians:audit-race-counts                 # all seeded states, current year
php artisan politicians:audit-race-counts --state=CA
php artisan politicians:audit-race-counts --json           # machine-readable
php artisan politicians:audit-race-counts --dispatch        # also trigger re-syncs (see below)
```

Counts our side as `Politician` rows where `state` matches, `is_running_candidate = true`,
and `political_office` matches "governor" but not "lieutenant governor". Exits
non-zero if any race is mismatched (over or under) — usable as a CI/monitoring gate.

### Auto-dispatch on under-count

Scheduled daily at 05:00 UTC (`routes/console.php`, after the 02:00 UTC
`sync-candidates` run) as `politicians:audit-race-counts --dispatch`.

States where we're **under** the expected count get pushed through
`App\Jobs\DispatchHotStatesSyncWorkflow` — the same GitHub `workflow_dispatch`
path `map:sync-hot-states` already uses for trending map states — which
triggers a targeted `sync-candidates.yml` run scoped to just those states,
ahead of the next scheduled full pass. A 20-hour cooldown cache
(`race_count_dispatched:{state}:{year}`) stops it from re-dispatching the
same still-unresolved gap every day.

Over-counts (stale/duplicate rows) are flagged in the audit output but not
auto-dispatched — a re-sync can't fix data we already have too much of.

## Part 2: RSS candidate discovery (Senate, Governor, House)

`App\Services\CandidateDiscovery\RssCandidateDiscoverySource`, driven by
`config/candidate_discovery_sources.php`, queries Google News RSS for
candidacy-announcement language and writes hits to `candidate_leads`. It is
a **discovery** source only — every lead still goes through
`CandidateLeadVerifier` (Ballotpedia → Wikipedia → Claude fallback) before
it can be promoted to a real candidate record, so a noisy headline-extraction
heuristic is an acceptable cost here.

```
php artisan candidates:discover-leads                          # all states, all offices
php artisan candidates:discover-leads --state=CA --office=house --dry-run
```

### Senate / Governor — statewide

One query per state per template (`query_templates` in the config),
substituting `{STATE_NAME}` and `{OFFICE}`. ~50 states × 2 offices × 3
templates ≈ 300 requests per full run.

### House — per-district, batched

A state-name-only query can't tell districts apart, so House uses
`house_query_templates` with `{DISTRICT_ORDINAL}`/`{DISTRICT_CODE}`
substitution instead, one query per known district. Districts come from
existing federal Representative rows (`districtsForState()` in the
discovery source) — no separate congressional-district reference table
needed, since every district already has an incumbent on file even before
we've tracked any challenger.

435 districts × 2 templates every run would be ~870 requests on top of the
~300 above — real rate-limit risk against Google News, so each run only
covers a rotating **1/`house_batch_divisor`** slice (default 7, i.e.
~62 districts/run), keyed off day-of-year so the full set cycles through
roughly every 7 days rather than none of them, always.

Wired into `sync-candidates.yml`'s existing "Discover candidate leads (RSS)"
step with no code change needed there — it already calls
`candidates:discover-leads` with no `--office` filter, so House rides along
automatically now that it's a configured office.

## Related files

- `database/migrations/2026_08_18_000001_create_governor_race_candidate_counts_table.php`
- `app/Models/GovernorRaceCandidateCount.php`
- `app/Console/Commands/SetGovernorRaceCandidateCount.php` (`election:set-race-count`)
- `app/Console/Commands/AuditGovernorRaceCounts.php` (`politicians:audit-race-counts`)
- `app/Jobs/DispatchHotStatesSyncWorkflow.php`
- `routes/console.php` (`05:00` schedule entry)
- `config/candidate_discovery_sources.php`
- `app/Services/CandidateDiscovery/RssCandidateDiscoverySource.php`
- `app/Console/Commands/DiscoverCandidateLeads.php` (`candidates:discover-leads`)
