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
- 11 workflows live in `.github/workflows/`: 6 scheduled data pipelines, 1 test
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

## GitHub Actions — all 11 workflows

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
| `refresh-map-candidates.yml` | `0 9 * * *` daily | Refresh map-facing candidate data + election results (`concurrency` group, won't overlap itself) | `states`, `election_year`, `include_results`, `create_missing`, `dry_run` |
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
gh workflow list                                # all 11 workflows + enabled state
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
