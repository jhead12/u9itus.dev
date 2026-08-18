# Map Governance-Level & Duplicate-Politician Fix Runbook

## Purpose

Manually re-run the production data repair for the "officeholders vanish from
the map" bug (fixed in commit `a38a4c8c`) without waiting for the daily
`refresh-map-candidates.yml` schedule. Use this when a specific politician is
reported missing from the map/directory and the fix needs to land sooner than
the next 09:00 UTC run.

## Background

`CongressGovService` (federal district lookups) and, more rarely,
`GoogleCivicService` (Governor/Mayor lookups) could write a Politician row
with the wrong `governance_level` — e.g. a sitting U.S. Representative saved
as `governance_level = 'Local'` instead of `'Federal'`. Because
`MapStateCandidatesController` filters strictly by `governance_level`, any
row with the wrong value is invisible on the public map even though every
other field (name, district, office) is correct. The same district-lookup
race condition can also create duplicate rows for the same real person.

Both write bugs are now fixed in code (`CongressGovService.php`,
`GoogleCivicService.php`), and the following two commands repair rows the
bug already wrote before the fix landed:

- `php artisan politicians:audit-data-integrity --fix --deactivate --limit=5000`
  — corrects `governance_level` for Federal/State/City-titled offices (plus
  pre-existing party/state/term_status normalization), and deactivates rows
  with unfixable artifact names.
- `php artisan politicians:merge-duplicates --force`
  — merges duplicate unclaimed rows for the same person (grouped by
  `full_name + political_office + state`) into a single canonical row.

Both commands are **idempotent** — safe to re-run. If nothing needs fixing,
they report 0 changes and exit cleanly.

## Prerequisites

- Railway CLI installed and logged in (`railway login`) as an account with
  access to the **Josh Head's Projects** workspace.
- No local setup needed beyond the CLI — `railway run` executes the command
  using your local checkout of this repo, with production's database
  credentials injected as environment variables. It does **not** require
  deploying first; it always uses whatever code is on disk in your local
  clone at the time you run it.

## Resolved IDs (Railway → `charismatic-caring` project, `production` environment)

| Resource | ID |
|---|---|
| Project | `5cf3bc6e-d919-4964-bd1d-83197b7d0441` |
| Environment (production) | `ed39c7d7-423a-4187-ba84-bd6536a2d686` |
| App service (`u9itus.dev`) | `e978c085-3799-47d8-8eda-8e155372f2d7` |

If these ever stop working (service recreated, project moved), re-resolve
them with:

```bash
railway status --json
```

and look for the service named `u9itus.dev` under the `production`
environment.

## Steps

Run these **in order**. Steps 1 and 3 are read-only dry runs — review their
output before running the corresponding fix step.

### 1. Sanity check — confirm you're hitting production

```bash
railway run \
  --project 5cf3bc6e-d919-4964-bd1d-83197b7d0441 \
  --environment ed39c7d7-423a-4187-ba84-bd6536a2d686 \
  --service e978c085-3799-47d8-8eda-8e155372f2d7 \
  -- php artisan tinker --execute="echo App\Models\Politician::count();"
```

Expect a number in the thousands (production has ~4,600+ politician rows as
of 2026-08). A number near 0 means the environment variables didn't resolve
to production — stop and re-check the IDs above before continuing.

### 2. Dry run the data-integrity audit

```bash
railway run \
  --project 5cf3bc6e-d919-4964-bd1d-83197b7d0441 \
  --environment ed39c7d7-423a-4187-ba84-bd6536a2d686 \
  --service e978c085-3799-47d8-8eda-8e155372f2d7 \
  -- php artisan politicians:audit-data-integrity --limit=5000
```

Read the summary line at the end
(`Audit complete: N scanned, N clean, N fixed, N governance_level fixed, N deactivated, N flagged`).
`governance_level fixed` will be 0 in this dry-run pass (it only reports
without `--fix`) — the count to actually watch for is how many
`governance_level '...' should be '...'` lines print above the summary.

### 3. Apply the audit fix

```bash
railway run \
  --project 5cf3bc6e-d919-4964-bd1d-83197b7d0441 \
  --environment ed39c7d7-423a-4187-ba84-bd6536a2d686 \
  --service e978c085-3799-47d8-8eda-8e155372f2d7 \
  -- php artisan politicians:audit-data-integrity --fix --deactivate --limit=5000
```

This also normalizes party/state/term_status values and deactivates rows
with clearly-invalid names — same as the daily CI step, just run on demand.

### 4. Dry run the duplicate merge

```bash
railway run \
  --project 5cf3bc6e-d919-4964-bd1d-83197b7d0441 \
  --environment ed39c7d7-423a-4187-ba84-bd6536a2d686 \
  --service e978c085-3799-47d8-8eda-8e155372f2d7 \
  -- php artisan politicians:merge-duplicates --dry-run
```

Review the list of `"Name" / Office / State — keeping id=X, merging id(s) Y`
lines. Only unclaimed rows (`user_id IS NULL`) are ever candidates for
merging — a real claimed profile is never touched or deleted.

### 5. Apply the merge

```bash
railway run \
  --project 5cf3bc6e-d919-4964-bd1d-83197b7d0441 \
  --environment ed39c7d7-423a-4187-ba84-bd6536a2d686 \
  --service e978c085-3799-47d8-8eda-8e155372f2d7 \
  -- php artisan politicians:merge-duplicates --force
```

### 6. Verify a specific politician

Replace `Espaillat` with the name reported missing:

```bash
railway run \
  --project 5cf3bc6e-d919-4964-bd1d-83197b7d0441 \
  --environment ed39c7d7-423a-4187-ba84-bd6536a2d686 \
  --service e978c085-3799-47d8-8eda-8e155372f2d7 \
  -- php artisan tinker --execute="App\Models\Politician::where('full_name','like','%Espaillat%')->get(['id','governance_level','district','state'])->each(fn(\$p)=>print_r(\$p->toArray()));"
```

Expect exactly one row, with `governance_level` matching the office
(`Federal` for a U.S. Rep/Senator, `State` for a Governor, `City` for a
Mayor).

Then check the live map API for that state directly (no Railway CLI needed):

```bash
curl -s "https://www.u9itus.com/api/v1/map/state-candidates?state=NY" | python3 -m json.tool | grep -A3 "NY-13"
```

(swap `NY`/`NY-13` for the relevant state/district). If it's still missing
after step 6 confirms the row is correct, the per-state map cache may not
have expired yet — it self-clears within an hour, or contact engineering to
force-clear it.

## Notes / Safety

- Every step above is **read from or writes to the live production
  database**. Steps 1, 2, and 4 are read-only; steps 3 and 5 write.
- Both fix commands are idempotent — running them again when there's
  nothing to fix is safe and reports 0 changes.
- `politicians:merge-duplicates` never touches a claimed profile
  (`user_id IS NOT NULL`) — only unclaimed, auto-generated ones.
- This whole sequence also runs automatically every day at 09:00 UTC via
  `.github/workflows/refresh-map-candidates.yml` — this runbook is only for
  when a fix needs to land sooner than that.
- If `railway run` output looks wrong (e.g. step 1 returns a small/local-
  looking number), stop and re-verify the project/environment/service IDs
  with `railway status --json` rather than proceeding.
