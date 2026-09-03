# Civic Source Registry

A registry that maps every US jurisdiction which can put something on a ballot
to its **official election authority and published URLs**, so the ballot-measure
pipeline can scrape (or, better, ingest structured feeds from) known-good
sources instead of discovering them by crawling.

Example of the kind of site this registry catalogues:
`https://voteinfo.net/november-3-2026-general-election` — a county elections
vendor page listing local measures.

- **Table**: `election_data_sources` (migration `2026_09_03_000001_create_election_data_sources_table.php`)
- **Model**: `App\Models\ElectionDataSource`
- **Config**: `config/civic.php` — verifier UA/timeout, curated state URLs, state capitals, vendor host map, HTML-adapter map
- **Commands** (all `civic:*`): `seed-jurisdictions`, `resolve-official-urls`, `pull-measures`, `scrape-measures`, `verify-sources`
- **Shared**: `App\Support\CivicVendorClassifier` (URL host → vendor slug), `App\Support\BallotMeasureWriter` (one dedup/provenance rule for every ingest path), `GoogleCivicService::voterInfoQuery()`
- **Adapters**: `App\Services\Civic\BallotMeasureAdapter` + `MeasureAdapterRegistry`; `Adapters\GenericHtmlMeasureAdapter` (per-vendor county pages) and `Adapters\WikipediaBallotMeasuresAdapter` (statewide, all states, one article — fills `yes_meaning`)
- **Measure content stays in**: `ballot_measures` (`App\Models\BallotMeasure`) — this registry only holds *where to look*.

## Why a registry instead of crawling

US ballot measures are administered at the **county** level (statewide
propositions come from the Secretary of State; local measures from the County
Elections Office / Registrar of Voters, occasionally a City Clerk). "One site
per district" is really one authority per **state** (51), per **county**
(~3,100), plus the subset of **cities / New England townships** that run their
own elections. The EAC counts ~6,500 local election jurisdictions nationwide.

That set is enumerable and mostly static. Enumerate it once, attach the
official URL to each row, verify on a schedule, and point scrapers at the
registry.

## Canonical key: OCD division IDs

Every row is keyed by its **Open Civic Data division ID**:

```
ocd-division/country:us/state:ca                      (state)
ocd-division/country:us/state:ca/county:los_angeles   (county)
ocd-division/country:us/state:ca/place:los_angeles    (municipal)
```

OCD IDs are the join key across every upstream source we use — Google Civic,
the Voting Information Project, and Ballotpedia all speak them — and they line
up with the `governance_level` / district model already in the app. The full US
list is the single master CSV
`github.com/opencivicdata/ocd-division-ids/blob/master/identifiers/country-us.csv`
(`id,name,census_geoid,…`; ~192k rows — states, counties, cities, council
districts, school districts). `civic:seed-jurisdictions --source=counties`
streams it and keeps only the top-level `.../county:<x>` rows.

## Table shape

| Column | Notes |
|---|---|
| `ocd_id` | unique — canonical key |
| `level` | `state` \| `county` \| `municipal` \| `township` \| `special` |
| `state` | USPS, uppercase |
| `jurisdiction_name` | "Los Angeles", "Maricopa County", "City of Austin" |
| `county_fips` / `place_fips` | 5- / 7-digit Census FIPS, for joining EAC + Census data |
| `authority_name` | "Los Angeles County Registrar-Recorder/County Clerk" |
| `vendor` | `voteinfo.net` \| `granicus` \| `ballottrax` \| `democracy_live` \| `homegrown` \| … |
| `platform_template` | scraper-adapter key — **one adapter serves a whole vendor family** |
| `elections_home_url`, `sample_ballot_url`, `ballot_measures_url`, `results_url` | the URLs scrapers want by name |
| `vip_feed_url`, `ballotpedia_url` | structured-source pointers |
| `urls` (json) | catch-all for anything else a source hands us |
| `source_of_record` | `eac` \| `nass` \| `census` \| `google_civic` \| `vip` \| `ballotpedia` \| `manual` |
| `robots_ok` | `null` until checked |
| `scrape_status` | `unverified` \| `ok` \| `blocked` \| `dead` \| `redirected` |
| `last_verified_at`, `last_scraped_at` | health tracking |

## Seed sources

| Source | Gives us | How |
|---|---|---|
| **Built-in state list** | 51 `state` rows + a curated official-elections URL for the states we're sure of | offline, in `SeedCivicJurisdictions::STATES` / `STATE_ELECTION_SITES` |
| **OpenCivicData master CSV** (`country-us.csv`) | ~3,100 `county` rows with OCD IDs + `county_fips` (from `census_geoid`) | `civic:seed-jurisdictions --source=counties` (streamed over HTTP, or `--file`) |
| **EAC EAVS jurisdiction list** | authority names, `county_fips`, New England townships | `--source=eac --file=…` — [eac.gov EAVS](https://www.eac.gov/research-and-data/election-administration-voting-survey) / [data.gov](https://catalog.data.gov/dataset/eac-data) — **stub, not implemented** |
| **Census places / gazetteer** | `municipal` rows + `place_fips` | `--source=municipalities` — pair with a curated allow-list — **stub, not implemented** |
| **NASS "Can I Vote" directory** | the 51 state elections sites, authoritative | curated into `config('civic.state_election_sites')` (**all 51**); state-row fallback for `civic:resolve-official-urls`, checked by `civic:verify-sources` (~10 bot-wall a scraper but load fine in a browser) |
| **Wikipedia — "<year> United States ballot measures"** | every state's certified statewide measures + a "result of a Yes vote" blurb, CC-BY-SA | `WikipediaBallotMeasuresAdapter` via `civic:scrape-measures` (state rows have `platform_template = 'wikipedia'`) |
| **Google Civic `voterInfoQuery`** | per-address `electionAdministrationBody` URLs **and** `Referendum` contests with `referendumTitle` / `referendumText` / `referendumUrl` | **`civic:resolve-official-urls` (done)** for URLs; `civic:pull-measures` (TODO) for measures. Only the *Representatives* endpoint was retired (Apr 2025); `voterInfoQuery` is still live via `GoogleCivicService::voterInfoQuery()`. Key: `GOOGLE_CIVIC_API_KEY`. |
| **Voting Information Project feeds** | the state-published XML behind Google Civic — official URLs + measures | `civic:pull-measures` — **TODO**. Fallback for when the Civic API degrades. |
| **Ballotpedia** | fullest single scrape target for measure text / analysis; API (paid) | key wired as `BALLOTPEDIA_API_KEY`; see [LOCAL_CANDIDATES_INTEGRATION.md](LOCAL_CANDIDATES_INTEGRATION.md) |

## Pipeline

```
1. civic:seed-jurisdictions   ← implemented (states + counties; municipal/eac stubbed)
      creates rows + OCD keys + level/state/name

2. civic:resolve-official-urls   ← implemented
      per row: Google Civic voterInfoQuery on a representative address
      (state → "<capital>, <ST>"; county → "<jurisdiction_name>, <ST>")
      → authority_name + electionAdministrationBody URLs (statewide for
      state rows, local_jurisdiction for county rows); infer `vendor` from
      the resolved hostname; state rows with no Civic URL fall back to
      config('civic.state_election_sites'). Stamps source_of_record=google_civic.

3a. civic:pull-measures   ← implemented (VIP feed path)
      per row: voterInfoQuery → `Referendum` contests → ballot_measures
      (via BallotMeasureWriter: dedup on state + title + election day,
      `source` never overwritten). Stamps last_scraped_at, scrape_status='ok'
      when measures were found.

3b. civic:scrape-measures   ← implemented (HTML-adapter path)
      for rows with a vendor / platform_template but no VIP feed: resolve a
      BallotMeasureAdapter and parse.
        - state rows → platform_template='wikipedia' → WikipediaBallotMeasuresAdapter
          reads the per-state tables in "<year> United States ballot measures"
          (all states, one article; its Description column fills yes_meaning).
        - county rows w/ a vendor → GenericHtmlMeasureAdapter (heuristic).
      Same BallotMeasureWriter, so it can't clobber a Ballotpedia/human
      measure; skips rows verify marked dead/blocked or robots-disallowed
      (except self-sufficient adapters like wikipedia, which fetch their own
      source).

4. civic:verify-sources   ← implemented
      HEAD/GET each URL following redirects → scrape_status
      (ok | redirected | blocked | dead); read robots.txt → robots_ok;
      re-classify vendor from the final host; stamp last_verified_at;
      --rewrite-redirects persists a resolved URL back into its column.
```

All four steps exist today, on both the Civic/VIP feed path and the HTML-adapter
path. `GenericHtmlMeasureAdapter` is a heuristic parser; a vendor that needs
bespoke selectors gets its own adapter class registered under a new key.

## `civic:seed-jurisdictions`

Idempotent — matches on `ocd_id`. Without `--refresh` it inserts new rows and
fills blank columns on existing ones; it never overwrites an `authority_name`
or URL that a later step or a human already set.

```bash
# All implemented sources (states + counties)
php artisan civic:seed-jurisdictions

# Just the 51 state rows
php artisan civic:seed-jurisdictions --source=states

# Counties for one state, from the live OCD CSV
php artisan civic:seed-jurisdictions --source=counties --state=CA

# Counties from a local mirror of the OCD master CSV (CI-friendly)
php artisan civic:seed-jurisdictions --source=counties --file=storage/app/ocd/country-us.csv

# Local jurisdictions from an EAC EAVS export (stub)
php artisan civic:seed-jurisdictions --source=eac --file=storage/app/eac/2024_eavs_jurisdictions.csv

# Preview, and re-pull URLs onto existing rows
php artisan civic:seed-jurisdictions --refresh --dry-run
```

Options: `--source` (`all` \| `states` \| `counties` \| `municipalities` \|
`eac`), `--state`, `--file`, `--refresh`, `--dry-run`.

> The URLs in `config('civic.state_election_sites')` are **seed hints**, not
> verified truth — `civic:verify-sources` is what confirms them.

## `civic:resolve-official-urls`

Reads `election_data_sources` rows and fills `authority_name`, the URLs, and
`vendor` from Google Civic's `voterInfoQuery`. Needs `GOOGLE_CIVIC_API_KEY`.

`voterInfoQuery` returns data only when the Voting Information Project has a
feed for that address + election — in practice the weeks around an election.
Between elections it returns nothing and the command reports **No data** for
those rows (state rows still get their `config('civic.state_election_sites')`
URL via the fallback path). Run it repeatedly as an election approaches.

```bash
# Everything due for a refresh (state + county)
php artisan civic:resolve-official-urls

# One state's counties, pacing the API
php artisan civic:resolve-official-urls --state=CA --level=county --sleep=400

# Force a specific Civic election id (see it via the run header / tinker
# GoogleCivicService::listUpcomingElections)
php artisan civic:resolve-official-urls --election-id=9468 --limit=100

# Only rows still missing URLs; preview
php artisan civic:resolve-official-urls --only-missing --refresh --dry-run
```

Options: `--state`, `--level` (`all`\|`state`\|`county`), `--election-id`,
`--stale-days=45` (skip rows verified more recently; `0` = ignore), `--limit=500`,
`--only-missing`, `--sleep=250` (ms between API calls), `--refresh`, `--dry-run`.

Idempotent. Without `--refresh` it only fills blank columns; `source_of_record`
is upgraded to `google_civic` only from `manual` / `census` / `nass` / empty.

### First run

```bash
php artisan migrate                       # creates election_data_sources
php artisan civic:seed-jurisdictions      # ~3,100 rows: 51 states + counties
php artisan civic:resolve-official-urls   # fill authority + URLs where Civic has a feed
php artisan civic:pull-measures           # ingest Referendum contests into ballot_measures
```

## `civic:pull-measures`

Reads `election_data_sources` rows, calls `voterInfoQuery`, and maps each
`Referendum` contest into `ballot_measures`:

| Civic field | `ballot_measures` column |
|---|---|
| `referendumTitle` | `title` (required) + `measure_number` parsed from it |
| `referendumSubtitle` / `referendumText` | `summary` |
| `district.name` (or the county row) | `county` |
| `election.electionDay` | `election_date` |
| `referendumUrl` (or the row's ballot URL) | `source_url` |
| — | `source = google_civic`, `status = upcoming` |

Dedup identity is `state + title + election_date` (calendar day), the same key
`ImportBallotMeasures` and the admin form use. Without `--refresh` an existing
measure only gets blank columns filled; `source` is never overwritten, so a
Ballotpedia- or human-authored measure keeps its provenance even when this pass
matches it. `yes_meaning` / `no_meaning` are left null — Civic gives the ballot
question, not a plain-language "what a Yes vote does"; that's a later enrichment.

```bash
php artisan civic:pull-measures
php artisan civic:pull-measures --state=CA --level=county --sleep=400
php artisan civic:pull-measures --election-id=9468 --refresh --dry-run
```

Options: `--state`, `--level`, `--election-id`, `--limit=500`, `--sleep=250`,
`--refresh`, `--dry-run`. The run summary reports `M/N rows with a feed had
measures` so you can see how many jurisdictions still need an HTML adapter.

## `civic:scrape-measures`

The HTML-adapter path for rows with a `vendor` / `platform_template` but no VIP
feed. Per row: `MeasureAdapterRegistry::for($row)` resolves an adapter
(`platform_template` first, else `config('civic.measure_adapters')[vendor]`,
else none → skipped), which fetches and parses the row's `ballot_measures_url`
(or `sample_ballot_url`).

**`WikipediaBallotMeasuresAdapter`** (`platform_template = 'wikipedia'`, set on
every state row by `civic:seed-jurisdictions`) fetches the "<year> United States
ballot measures" article once per run and returns just the target state's table
rows: `Measure` → title + number, `Description (Result of a "yes" vote)` →
`summary` **and `yes_meaning`**, `Date`/`Status` mapped. Covers all ~40 states
with statewide measures from one page; year via `config('civic.wikipedia.year')`.

**`GenericHtmlMeasureAdapter`** (county rows with a vendor) is deliberately
conservative: only short heading-like nodes that *start* with a measure label +
designator ("Measure A", "Proposition 64"), following block(s) as the summary,
caps at 60, reads the date from the page title or a URL slug. `source = html_scrape`.

Row selection excludes `scrape_status` `dead` / `blocked` and `robots_ok = false`
for rows we fetch by their own URL; a `platform_template` row (e.g. wikipedia)
is always included since its adapter fetches its own source. Run
`civic:verify-sources` first for the URL-fetch adapters.

```bash
php artisan civic:scrape-measures
php artisan civic:scrape-measures --vendor=voteinfo_net --state=CA
php artisan civic:scrape-measures --only-empty --election-date=2026-11-03
php artisan civic:scrape-measures --refresh --dry-run
```

Options: `--state`, `--vendor`, `--election-date` (fallback when the page has no
parseable date), `--only-empty` (skip states that already have upcoming
measures), `--limit=300`, `--sleep=500`, `--refresh`, `--dry-run`.

## `civic:verify-sources`

Health-checks the URL columns on each row and writes back `scrape_status`,
`robots_ok`, `last_verified_at`, and a re-classified `vendor`. No API key —
just outbound HTTP under `config('civic.verifier.user_agent')`.

Per row it checks every non-null URL (HEAD, GET fallback on 405/501), following
up to 5 redirects. The **primary URL** (`ballot_measures_url` →
`sample_ballot_url` → `elections_home_url` → `results_url`, first non-null) sets
the row's `scrape_status`:

| Result | `scrape_status` |
|---|---|
| 2xx, no redirect (trailing-slash / scheme change ignored) | `ok` |
| 2xx after landing on a genuinely different URL | `redirected` |
| 401 / 403 / 429 / any 5xx / timeout / TLS error | `blocked` |
| 404 / 410 / DNS failure | `dead` |

`robots_ok` is set from the primary URL's host `robots.txt` (host-cached per
run; missing/unreadable robots.txt ⇒ allowed). `vendor` is re-derived from the
final post-redirect hosts via `CivicVendorClassifier` and updated if it changed.

A `blocked` result is frequently a bot wall (Akamai/Incapsula 403 on any
non-browser UA), not a dead page — several Secretary-of-State sites do this. It
means "an automated scrape will fail here", so treat those rows as needing a
hand-picked alternate URL or a per-host fetch strategy.

```bash
php artisan civic:verify-sources
php artisan civic:verify-sources --state=CA --level=county
php artisan civic:verify-sources --stale-days=7 --rewrite-redirects
php artisan civic:verify-sources --dry-run
```

Options: `--state`, `--level`, `--stale-days=7` (`0` = all), `--limit=1000`,
`--sleep=200`, `--timeout=` (default `config('civic.verifier.timeout')`),
`--rewrite-redirects` (persist a redirect target into its column), `--dry-run`.

## Vendor-family scrapers

Many a county's "official site" is actually a vendor subdomain
(`voteinfo.net`, Granicus, BallotTrax/DFM, Democracy Live, Simply Voting).
`civic:verify-sources` tags the row's `vendor` from its URL host; one
`BallotMeasureAdapter` per vendor family then covers every county on it.

To add a bespoke adapter: implement `App\Services\Civic\BallotMeasureAdapter`
(`key()` + `fetchMeasures()`), register it in `MeasureAdapterRegistry`'s adapter
list, and point the vendor at its key in `config('civic.measure_adapters')`. A
row can also name an adapter key directly in `election_data_sources.platform_template`,
which wins over the vendor map.

## Scheduling

Add to `routes/console.php`, mirroring the existing `imports:*` cadence.

```php
// Monthly — jurisdiction set barely changes
Schedule::command('civic:seed-jurisdictions')->monthlyOn(1, '02:00')->withoutOverlapping();
// Weekly — refresh official URLs; runs more usefully as an election nears
Schedule::command('civic:resolve-official-urls --stale-days=45 --sleep=400')
    ->weeklyOn(1, '02:30')->withoutOverlapping()->runInBackground();
// Daily around elections — pull measures from the VIP feed
Schedule::command('civic:pull-measures --sleep=400')
    ->dailyAt('02:15')->withoutOverlapping()->runInBackground();
// Weekly URL health check — before the scrape so it skips known-dead rows
Schedule::command('civic:verify-sources --stale-days=7 --sleep=300')
    ->weeklyOn(1, '03:00')->withoutOverlapping()->runInBackground();
// Weekly HTML scrape for jurisdictions with no VIP feed
Schedule::command('civic:scrape-measures --sleep=600')
    ->weeklyOn(1, '03:45')->withoutOverlapping()->runInBackground();
```

## Data availability (why a state can be empty)

The automated feeds are **dry outside the election window**. Google Civic's VIP
feed only carries a general election — its dates *and* its `Referendum`
contests — from roughly 30 days out. Vote Smart's API was retired in 2024.
Ballotpedia needs a paid key. So months before an election:

- **`state_election_dates`** — run `elections:sync-dates` (scheduled weekly).
  By default it seeds the legally-fixed federal General date (Tue after 1st Mon
  of Nov, even years) for every state nothing else covered, `source = statutory`;
  `--no-statutory` turns that off.
- **`ballot_measures`** (statewide) — run `civic:scrape-measures` (scheduled
  weekly). The Wikipedia adapter pulls every state's certified statewide
  measures with plain-language `yes_meaning` from one CC-BY-SA article, months
  before the VIP feed has anything. `civic:pull-measures` (VIP) and any curated
  CSV (`ballot-measures:import`, e.g. `database/seeders/ca-2026-ballot-measures.csv`,
  `source = ca_sos`) reconcile with it — `BallotMeasureWriter` dedups on
  state + election-day + (title **or** measure number), so a fuller title from
  one source enriches the other instead of duplicating.

The WebMCP tools (`u9itus_list_ballot_measures`, `u9itus_upcoming_elections`)
read these tables directly, so a fresh deploy shows nothing until the commands
above have run **on that environment**.

## Legal / etiquette

- Prefer structured feeds (VIP, Google Civic, state SoS open data, Ballotpedia
  API) over HTML scraping.
- Honour `robots.txt` — that's what the `robots_ok` column is for.
- Government election pages are public record, but still rate-limit, cache, and
  set a descriptive User-Agent.
- Ballotpedia content is licensed — use their API tier, don't bulk-scrape.

## Open items

- [x] `civic:seed-jurisdictions` — states + counties (with `county_fips`).
- [x] `civic:resolve-official-urls` + `GoogleCivicService::voterInfoQuery()`.
- [x] `civic:pull-measures` — Civic/VIP `Referendum` → `ballot_measures`.
- [x] `civic:verify-sources` — URL health, redirects, robots.txt, vendor re-classify.
- [x] `civic:scrape-measures` + `GenericHtmlMeasureAdapter` — HTML path for rows with no VIP feed.
- [x] `WikipediaBallotMeasuresAdapter` — all-states statewide measures + `yes_meaning`, one article.
- [x] `config('civic.state_election_sites')` — all 51 states (verify: 37 ok / 4 redirect / 10 bot-walled, 0 dead).
- [ ] Implement `seedMunicipalities()` — Census places + curated allow-list.
- [ ] Implement `seedFromEac()` — EAVS jurisdiction export → `authority_name`, `county_fips`, townships.
- [ ] Tune `GenericHtmlMeasureAdapter` against real vendor fixtures; add a bespoke adapter where the heuristic misses.
- [ ] `civic:enrich-measures` — derive `no_meaning` (LLM) for rows that only have `yes_meaning`.
- [ ] Wire an `/admin/imports`-style panel over `scrape_status` / `last_verified_at` counts.
- [ ] Per-state SoS **candidate-list** URLs (Wikipedia + FEC + Open States cover most of the data; SoS filing lists are the gap for challengers).
- [ ] Revisit the county "representative address" — `"<county>, <ST>"` geocodes fine for Civic, but a county-seat street address may resolve the `local_jurisdiction` body more reliably in split jurisdictions.
- [ ] `voterInfoQuery` auto-picks the first Civic election id per state; when a state has concurrent elections (primary + special), pass `--election-id` explicitly.
