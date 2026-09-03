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
- **Seed command**: `php artisan civic:seed-jurisdictions` (`App\Console\Commands\SeedCivicJurisdictions`)
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
| **NASS "Can I Vote" directory** | the 51 state elections sites, authoritative | used by `civic:resolve-official-urls` / `civic:verify-sources` to fill + check `STATE_ELECTION_SITES` — **stub** |
| **Google Civic `voterInfoQuery`** | per-address `electionAdministrationBody` URLs **and** `Referendum` contests with `referendumTitle` / `referendumText` / `referendumUrl` | `civic:resolve-official-urls`, `civic:pull-measures` — **stubs**. Only the *Representatives* endpoint was retired (Apr 2025); `voterInfoQuery` is still live. Key already wired as `GOOGLE_CIVIC_API_KEY` (`App\Services\GoogleCivicService`). |
| **Voting Information Project feeds** | the state-published XML behind Google Civic — official URLs + measures | `civic:pull-measures` — **stub**. Fallback for when the Civic API degrades. |
| **Ballotpedia** | fullest single scrape target for measure text / analysis; API (paid) | key wired as `BALLOTPEDIA_API_KEY`; see [LOCAL_CANDIDATES_INTEGRATION.md](LOCAL_CANDIDATES_INTEGRATION.md) |

## Pipeline

```
1. civic:seed-jurisdictions   ← implemented (states + counties; municipal/eac stubbed)
      creates rows + OCD keys + level/state/name

2. civic:resolve-official-urls   ← TODO
      per row: Google Civic voterInfoQuery on a representative address
      (county-seat centroid) → electionAdministrationBody URLs;
      infer `vendor` from the resolved hostname; NASS list for state rows

3. civic:pull-measures   ← TODO
      where Civic / VIP already return Referendum contests, ingest straight
      into ballot_measures; HTML-scrape only jurisdictions with no feed,
      dispatched through the `platform_template` adapter

4. civic:verify-sources   ← TODO (weekly)
      HEAD-check every URL, honour robots.txt, flag dead/redirected,
      re-classify vendor, stamp last_verified_at
```

Only step 1 exists today. Steps 2–4 are named here so the column set and the
`source_of_record` / `scrape_status` enums already anticipate them.

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

> The URLs in `STATE_ELECTION_SITES` are **seed hints**, not verified truth —
> `civic:verify-sources` is what confirms them.

### First run

```bash
php artisan migrate            # creates election_data_sources
php artisan civic:seed-jurisdictions --dry-run
php artisan civic:seed-jurisdictions
```

## Vendor-family scrapers

Many a county's "official site" is actually a vendor subdomain
(`voteinfo.net`, Granicus, BallotTrax/DFM, Democracy Live, Simply Voting). Write
**one adapter per `platform_template`**, not per county — once a `voteinfo.net`
scraper works for one county it works for all of them. The registry's job is to
tell the scraper which adapter to run for a given `ocd_id`.

## Scheduling (once steps 2–4 land)

Add to `routes/console.php`, mirroring the existing `imports:*` cadence:

```php
// Monthly — jurisdiction set barely changes
Schedule::command('civic:seed-jurisdictions')->monthlyOn(1, '02:00')->withoutOverlapping();
// After a seed — refresh official URLs
Schedule::command('civic:resolve-official-urls --stale-days=45')->weeklyOn(1, '02:30')->withoutOverlapping();
// Weekly health check
Schedule::command('civic:verify-sources')->weeklyOn(1, '03:00')->withoutOverlapping();
// Around elections — pull measures more often
Schedule::command('civic:pull-measures')->dailyAt('02:15')->withoutOverlapping();
```

## Legal / etiquette

- Prefer structured feeds (VIP, Google Civic, state SoS open data, Ballotpedia
  API) over HTML scraping.
- Honour `robots.txt` — that's what the `robots_ok` column is for.
- Government election pages are public record, but still rate-limit, cache, and
  set a descriptive User-Agent.
- Ballotpedia content is licensed — use their API tier, don't bulk-scrape.

## Open items

- [ ] Implement `seedMunicipalities()` — Census places + curated allow-list.
- [ ] Implement `seedFromEac()` — EAVS jurisdiction export → `authority_name`, `county_fips`, townships.
- [ ] Build `civic:resolve-official-urls` on `GoogleCivicService` (needs a `voterInfoQuery` method — only `getElectionsByAddress` exists today).
- [ ] Build `civic:pull-measures` and the first `platform_template` adapter (`voteinfo.net`).
- [ ] Build `civic:verify-sources` + wire an `/admin/imports`-style health panel.
- [ ] Decide the "representative address" per county for `voterInfoQuery` (county-seat centroid vs. a known valid residential address).
