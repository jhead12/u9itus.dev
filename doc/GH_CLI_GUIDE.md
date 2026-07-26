# GitHub CLI (`gh`) Guide for u9itus.dev

A reference for the `gh` commands most useful in *this* repo, built from
commands actually used while debugging the donor-enrichment pipeline outage on
2026-07-21 (`jhead12/u9itus.dev`, default branch `master`). Run `gh <command>
--help` for the full flag list — this is "what to reach for and why," not
exhaustive docs.

## This repo's shape, as `gh` sees it

- Default branch: `master`. No `.x` release branches in active use, but
  `tests.yml` watches `master` and any `*.x` branch on push.
- No PR/issue templates and no `CODEOWNERS` — `gh pr create` won't prefill a
  body, so write one.
- Labels are the GitHub defaults (`bug`, `enhancement`, `question`,
  `wontfix`, `duplicate`, `invalid`, `help wanted`) — nothing repo-custom yet.
- 13 workflows live in `.github/workflows/`: 8 scheduled data pipelines, 1 test
  suite, 1 on-demand incident-response job, and 3 event-triggered automations
  borrowed from Laravel's shared org workflows — see the breakdown below.
  This is a civic data platform, so a lot of the CI here means "did last
  night's scrape/enrich run correctly," not just "did the test suite pass."

## Setup

```bash
gh auth status                     # confirm you're authed against jhead12/u9itus.dev
gh repo view --json defaultBranchRef,nameWithOwner   # sanity-check what `gh` thinks is "this repo"
```

## Pull requests

```bash
gh pr create --title "..." --body "..."
gh pr status
gh pr checks 123                 # did tests.yml pass on this PR?
gh pr diff 123
gh pr view 123 --web
```

Right now `gh pr list` shows a real, actionable backlog worth knowing about:

```bash
gh pr list --state open --limit 10
```

Five of the open PRs are Dependabot bumps (`react`/`react-dom`, `tailwindcss`,
`axios`, `pusher-js`, `autoprefixer`, plus a GitHub Actions bump for
`actions/setup-node`). These correspond to the 75 vulnerabilities
(9 critical, 27 high) GitHub flagged on `master`. Useful triage flow:

```bash
gh pr list --author app/dependabot --state open
gh pr checks <number>              # confirm tests.yml is green before merging
gh pr merge <number> --squash
```

PR review comments aren't exposed by `gh pr view`, so drop to the API:

```bash
gh api repos/jhead12/u9itus.dev/pulls/123/comments
```

## Issues

```bash
gh issue list --state open
gh issue create --title "..." --body "..." --label bug
gh issue view 45
```

Note `.github/workflows/issues.yml` triggers on `issues: types: [labeled]` —
labeling an issue in this repo isn't just organizational, it may fire
automation. Check that workflow before assuming a label is "just a label."

## GitHub Actions — all 13 workflows

They fall into three groups, and `gh` interacts with each one differently.

### Group 1 — scheduled data pipelines (dispatchable, the ones you'll touch most)

Every workflow in this group has `workflow_dispatch`, so you never have to
wait for the cron to test a fix or backfill data.

| Workflow | Cron (UTC) | Purpose | Key `-f` inputs |
|---|---|---|---|
| `sync-candidates.yml` | `0 2 * * *` daily | Sync/enrich candidate profiles per state, optionally scrape election results | `state`, `states`, `include_former`, `election_year`, `include_results`, `create_missing`, `dry_run` |
| `enrich-donor-snapshots.yml` | `0 3 * * *` daily | Populate `politician_donor_snapshots` (FEC/OpenSecrets) — the job this session's fix unblocked | `limit` (default 200), `stale_hours`, `politician`, `force`, `dry_run` |
| `validate-profile-photos.yml` | `30 3 * * *` daily | Quarantine/validate candidate photos | `state`, `limit`, `include_claimed`, `fix_invalid`, `dry_run` |
| `refresh-candidate-news.yml` | `0 */6 * * *` every 6h | Refresh news articles per candidate | `stale_hours`, `limit`, `dry_run` |
| `refresh-viral-moments.yml` | `7 */6 * * *` every 6h | Fetch + score YouTube viral-moment clips per politician and feature the top one (`concurrency` group, off the :00 stampede) | `limit` (default 200), `stale_hours`, `politician`, `force`, `dry_run` |
| `refresh-map-candidates.yml` | `0 9 * * *` daily | Refresh map-facing candidate data + election results (`concurrency` group, won't overlap itself) | `states`, `election_year`, `include_results`, `create_missing`, `dry_run` |
| `sync-election-dates.yml` | `0 6 1 * *` monthly (1st) | Sync real election dates per state from Vote Smart | `election_year` (default 2026), `state`, `dry_run` |
| `sync-census-demographics.yml` | `0 5 * * 0` weekly (Sun) | Pull ACS Census demographics per city/district | `year` (ACS vintage), `state`, `dry_run` |

Almost every input above defaults to something sane, and almost every
workflow has a `dry_run` boolean — always reach for `dry_run=true` first when
learning a pipeline's shape, since it reports without writing to the database.

This is the exact sequence used to diagnose and fix the donor-snapshot outage:

```bash
gh run list --workflow=enrich-donor-snapshots.yml --limit 5
```

```
completed  failure  Enrich Donor Snapshots  master  schedule  29800181750  1m17s  2026-07-21T04:03:30Z
completed  success  Enrich Donor Snapshots  master  schedule  29716475925  2m2s   2026-07-20T04:16:34Z
```

That output alone pinpointed exactly when a healthy job started failing.

```bash
gh run view 29800181750 --log-failed   # only the failed step's logs — this found the SQLSTATE 1091 error
```

Then, once the migration was fixed, re-running by hand with the same inputs
the cron would use (rather than waiting for `0 3 * * *`):

```bash
gh workflow run enrich-donor-snapshots.yml -f dry_run=true      # sanity-check first
gh workflow run enrich-donor-snapshots.yml -f politician=1c8a2-united-states-representative-jefferson-shreve
gh workflow run enrich-donor-snapshots.yml -f force=true -f limit=200
gh run watch $(gh run list --workflow=enrich-donor-snapshots.yml --limit 1 --json databaseId -q '.[0].databaseId')
```

### Sibling job: `politicians:enrich-profiles` (in-app scheduler only — no GA workflow yet)

The session that followed the donor fix added a sibling enrichment command,
`politicians:enrich-profiles`, that fetches each politician's own
official/campaign website and extracts contact methods (phone/email/fax),
office addresses (**residential rejected** — the `address_kind` enum is
`office|district|mailing` only), social/newsletter links (including Substack
posts), and donation page URLs (link-out only, never embedded). It mirrors
`enrich-donors` exactly: signature
`{--limit=200} {--stale-hours=48} {--politician=} {--force} {--dry-run}`,
staleness gated on the latest `profile_enrichment_run.enriched_at`. Writes
five new tables (`profile_enrichment_runs` + four `profile_*` fact tables) and
reuses `candidate_news_articles` for newsletter posts.

It's slotted into the in-app scheduler at `0 4 * * *` daily, right after the
`0 3` donor job, so it shows up in `php artisan schedule:list`:

```bash
php artisan schedule:list | grep enrich-profiles
# 0   4   * * *   php artisan politicians:enrich-profiles --stale-hours=48 --limit=200
```

But — unlike the eight Group 1 pipelines above — it has **no
`.github/workflows/enrich-profiles.yml` yet**, so the `gh workflow run` pattern
does not apply:

```bash
gh workflow run enrich-profiles.yml     # fails — no such workflow file exists
gh run list --workflow=enrich-profiles.yml   # nothing to list
```

To run it on demand today, invoke the artisan command directly rather than via
`gh`:

```bash
php artisan politicians:enrich-profiles --politician=<slug> --force
php artisan politicians:enrich-profiles --dry-run --limit=10   # report only, no DB writes
php artisan politicians:enrich-profiles --stale-hours=48 --force
```

If you want GH-CLI parity with the other Group 1 pipelines (a
`workflow_dispatch` workflow that the in-app `0 4` schedule backstops, the
same way `enrich-donor-snapshots.yml` backstops `enrich-donors`), copy
`enrich-donor-snapshots.yml` and point its `run:` step at
`php artisan politicians:enrich-profiles` with the same `-f` inputs
(`limit`, `stale_hours`, `politician`, `force`, `dry_run`) — then the
`gh run list --workflow=enrich-profiles.yml` / `gh workflow run
enrich-profiles.yml -f dry_run=true` drill works identically to the donor job.

### More in-app-scheduler-only enrichers (no GA workflow yet)

Three more enrichment commands follow the same `enrich-profiles` shape — same
`{--limit=200} {--stale-hours=48} {--politician=} {--force} {--dry-run}`
signature, slotted into the in-app scheduler in `routes/console.php`, but with
**no `.github/workflows/*.yml` behind them**, so `gh workflow run` does not
apply. Reach for `php artisan` directly:

```bash
# C-SPAN video clips — Playwright scrape (scripts/scrape-cspan.js) because
# c-span.org renders search client-side. Clips have no view counts, so they
# score 0 and surface in the list by recency; the YouTube clip stays featured.
# Scheduled 05:30 daily, after the 05:00 YouTube pass.
php artisan politicians:enrich-cspan-moments --politician=<slug> --force
php artisan politicians:enrich-cspan-moments --dry-run --limit=10

# Inferred issue/discourse badges — rolls up each politician's verified news
# (stored topic_key) + viral-moment clip titles + Vote Smart NPAT positions
# into per-topic scores (keyword tier + Claude haiku LLM fallback) and grants
# an `inferred_discourse` profile badge for topics crossing the threshold.
# Scheduled 06:30 daily, after the YouTube/C-SPAN/marketing passes.
php artisan politicians:enrich-issue-badges --politician=<slug> --dry-run   # preview signals + would-grant
php artisan politicians:enrich-issue-badges --politician=<slug> --force     # write signals + badges

# Marketing content agent — auto-drafts blog Posts from a politician's recent
# news/viral moments. Drafts land as PendingApproval (nothing auto-published).
# Scheduled 06:00 daily; gated on config('u9itus.marketing.drafting.enabled').
php artisan marketing:draft-posts --limit=20
```

They show up in `schedule:list` alongside `enrich-profiles`:

```bash
php artisan schedule:list | grep -E 'enrich-cspan|enrich-issue|draft-posts'
# 30  5  * * *   php artisan politicians:enrich-cspan-moments --stale-hours=48 --limit=200
# 0   6  * * *   php artisan marketing:draft-posts --limit=20
# 30  6  * * *   php artisan politicians:enrich-issue-badges  --stale-hours=48 --limit=200
```

If you want GH-CLI parity for any of these, the recipe is the same as for
`enrich-profiles`: copy `refresh-viral-moments.yml` (the closest existing
analogue — same option surface) and repoint its `run:` step at the new
command, keeping the `limit` / `stale_hours` / `politician` / `force` /
`dry_run` inputs. Then `gh workflow run <name>.yml -f dry_run=true` works the
same as it does for the YouTube moments job.

### Account deletion Stripe cleanup — migration + queue worker (no GA workflow, and never will be)

`UserDeletionService::archiveAndDelete()` (previously reachable only through
the admin "delete user" web action, at `DELETE /admin/users/{user}`) now
refunds unused politician/citizen credit balances synchronously, then
snapshots the Stripe objects that still need cleanup (saved cards, the Stripe
Customer, a voter's Connect payout account) onto the `deleted_accounts` row
and dispatches `ProcessAccountDeletionStripeCleanupJob` onto the queue. This
is a one-time schema change, a queued job, and a console command triggered by
an admin action — not a recurring pipeline — so unlike the enrichers above,
there's no `gh workflow run` parity to build here; these commands are it.

```bash
# One-time: adds stripe_cleanup_plan/status/timestamps/error to deleted_accounts
php artisan migrate --path=database/migrations/2026_07_25_000001_add_stripe_cleanup_fields_to_deleted_accounts_table.php
```

New `users:delete` console command gives CLI/scripted access to the same
pipeline the admin dashboard uses, no browser session required. It requires
`--admin=` (id or email) since the audit log needs a real acting admin —
`archiveAndDelete()`'s own `$deletedBy = null` self-delete path has a known
bug (it logs using the just-deleted user, which trips the audit log's FK),
so this command always resolves and validates a real, separate admin account
first, the same way `AdminController::deleteUser()` does:

```bash
php artisan users:delete someone@example.com --admin=admin@u9itus.com --reason="policy violation"
php artisan users:delete 42 --admin=1 --force            # skip the confirmation prompt (scripted use)
```

`QUEUE_CONNECTION=database` in this repo's `.env`/`.env.production`, so the
cleanup job sits in the `jobs` table until a worker picks it up — deleting a
user does **not** touch Stripe synchronously beyond the refund step:

```bash
php artisan queue:work            # process ProcessAccountDeletionStripeCleanupJob (and everything else queued)
php artisan queue:work --once     # process a single queued job, useful when testing one deletion by hand
```

If a cleanup run partially fails (e.g. Stripe is down when detaching a card),
`deleted_accounts.stripe_cleanup_status` is set to `partially_completed` with
the error in `stripe_cleanup_error` rather than silently retrying — the job
has `tries = 1` on purpose (money-adjacent jobs in this repo never
auto-retry, see `ProcessBatchPayoutsJob` for the same pattern). Re-running it
for one account is a deliberate re-dispatch, not automatic:

```bash
php artisan tinker --execute="\App\Jobs\ProcessAccountDeletionStripeCleanupJob::dispatch(\App\Models\DeletedAccount::find(<id>));"
```

### Group 2 — on-demand incident response

`repair-broken-profile.yml` has no schedule at all — it only runs when
dispatched, either by hand or via `repository_dispatch` (e.g. an alerting
system POSTing a `profile.repair` event). This is the one to reach for when a
candidate profile page 500s in production:

```bash
gh workflow run repair-broken-profile.yml -f slug=<politician-slug> -f dry_run=true
```

Its `concurrency` group is keyed by slug (`repair-profile-<slug>`), so firing
it twice for the same slug queues the second run instead of racing it — safe
to retry if you're not sure the first dispatch went through.

### Group 3 — event-triggered repo automation (you don't dispatch these)

Three workflows call Laravel's shared org-level reusable workflows
(`uses: laravel/.github/.github/workflows/...@main`) and only fire on GitHub
events, not on a schedule:

| Workflow | Trigger | What it does |
|---|---|---|
| `issues.yml` | an issue gets **labeled** | Runs Laravel's `help-wanted` automation |
| `pull-requests.yml` | a PR is **opened** (`pull_request_target`) | Runs Laravel's `uneditable` check on protected files |
| `update-changelog.yml` | a **release** is published | Auto-updates `CHANGELOG.md` |

None of these three declare `workflow_dispatch`, so this will fail — worth
knowing so you don't burn time on it:

```bash
gh workflow run issues.yml
# could not create workflow dispatch event: HTTP 422: Workflow does not have 'workflow_dispatch' trigger
```

To exercise them you have to trigger the underlying event instead (label an
issue, open a PR, publish a release) — but you can still audit past runs the
normal way:

```bash
gh run list --workflow=pull-requests.yml --limit 5
```

### `tests.yml` — the one workflow that gates every PR

Runs on every `push` to `master`/`*.x`, every `pull_request`, and nightly at
`0 0 * * *`. Single job, PHP 8.4 matrix, does the full install → build →
`php artisan test --coverage-clover` cycle and uploads a coverage artifact.
`gh pr checks <number>` is really asking "is `tests.yml` green on this PR?"

```bash
gh run list --workflow=tests.yml --branch=master --limit 5
gh run view <run-id> --log-failed        # a red PR check, fastest way to see why
```

### General run/workflow commands

```bash
gh workflow list                                # all 13 workflows + enabled state
gh run list --limit 15                          # most recent runs across all workflows
gh run rerun <run-id> --failed                  # re-run only the failed jobs
```

## The escape hatch: `gh api`

Anything not wrapped by a subcommand — PR review comments, per-job Action
logs, repo vulnerability alerts — is one `gh api` call away, with auth handled
for you:

```bash
gh api repos/jhead12/u9itus.dev/pulls/123/comments
gh api repos/jhead12/u9itus.dev/actions/runs/29800181750/jobs
gh api repos/jhead12/u9itus.dev/dependabot/alerts --paginate
```

## Useful global patterns

- `--json field1,field2` on list/view commands gives scriptable output; pipe
  into `jq`:
  ```bash
  gh run list --workflow=enrich-donor-snapshots.yml \
    --json databaseId,conclusion,createdAt --limit 5 | jq '.[] | select(.conclusion=="failure")'
  ```
- `-R owner/repo` targets a different repo without `cd`-ing there.
- `gh <command> --help` beats searching the web docs mid-task.

## Suggested practice, using this repo

1. Run `gh pr list --author app/dependabot` and work through the 5 open
   dependency bumps — check `gh pr checks` on each, merge the safe ones.
2. Run `gh run list --workflow=<name>.yml --limit 10` for every workflow in
   the Group 1 table — is each one healthy? Any failures worth investigating
   with `--log-failed`? (This is exactly how the donor-snapshot outage was
   first spotted.)
3. Read `.github/workflows/enrich-donor-snapshots.yml`'s `workflow_dispatch.inputs`
   block, then dispatch it yourself with `dry_run=true` so it reports without
   touching the database — a safe way to learn a pipeline's shape before ever
   running it for real. Do the same for `sync-candidates.yml` with a single
   `state` set, so you're not triggering a full 50-state run on your first try.
4. Run `gh workflow run issues.yml` on purpose and read the 422 error — it's
   the fastest way to internalize which workflows are dispatchable and which
   are purely event-driven.
5. Run `gh run list --workflow=tests.yml --limit 10` and open the most recent
   run with `gh run view <id>` — compare its job/step names against the
   `steps:` list in `.github/workflows/tests.yml` so the YAML and the CLI
   output map onto each other in your head.
6. Try `gh api repos/jhead12/u9itus.dev/dependabot/alerts --paginate | jq '.[] | {severity: .security_advisory.severity, summary: .security_advisory.summary}'`
   to see the raw shape behind the "75 vulnerabilities" banner GitHub shows on
   the repo.
7. Run `php artisan schedule:list | grep enrich-profiles` to see the
   `politicians:enrich-profiles` job (04:00 daily) — then run it on demand with
   `--dry-run` to learn its shape. Note this one is **in-app-scheduler only**
   (no `enrich-profiles.yml` workflow), so you reach for `php artisan`, not
   `gh workflow run`. It's not alone — `politicians:enrich-cspan-moments`,
   `politicians:enrich-issue-badges`, and `marketing:draft-posts` follow the
   same pattern (see the "More in-app-scheduler-only enrichers" section). A
   good reminder to check `gh workflow list` before assuming every cron job has
   a workflow behind it.
