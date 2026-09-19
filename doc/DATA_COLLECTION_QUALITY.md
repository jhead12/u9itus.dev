# Candidate collection quality

The collection workflows distinguish primary advancement, election results, and
current officeholding. `advanced_to_general` keeps a candidate running. An explicit
`won` result requires `election_stage=general` or `special`; a primary `won` becomes
advancement. An ambiguous win is logged for review without changing the profile.
A general/special winner gets `won_at` and `term_status=active` until an officeholder
source establishes that the term has begun. A currently seated official keeps
that status, including when running for reelection. Results for a different office
cannot overwrite the person's current office.

## Publication and review

The results-import `--create-missing` option is opt-in in both collection workflows.
New result-only profiles are inactive and unpublished. Their source URL, result,
stage, and import timestamp are recorded in `page_settings.import_review`.
Review the source, identity, office, district, election stage/date, and duplicate
matches before activating/publishing them through the existing administration flow.
This gate concerns the results importer; curated officeholder and other candidate
import paths retain their existing behavior.

Cross-office cleanup now reports unresolved matches without marking candidates
eliminated. Federal reconciliation no longer treats absence from the officeholder
feed as an election loss. Duplicate candidates in the map refresh are queued for
review rather than automatically merged.

## Audits and workflow behavior

- `sync-candidates` runs an independent final audit even if an import or enrichment
  job fails. The map refresh also audits after earlier step failures.
- Audits scan all records by default (`--limit=0`) and fail on unresolved violations.
  `--max-violations` can explicitly set a tolerated count for manual audits.
- `--dry-run` prevents audit fixes/deactivation even when those flags are supplied.
  Shared workflow setup no longer runs schema migrations; deployment owns migrations.
- Audits write JSON summaries and logs to `storage/app/qa/candidate-data`. Actions
  retains these as artifacts for 14 days and publishes counts in the run summary.
- Scrape jobs retain Ballotpedia/voter-guide JSON snapshots for 14 days, including
  when later imports fail. These permit review of skipped or ambiguous outcomes.
- Multi-state map results and audits process every requested state and use separate
  files. A failed state yields a failing step but does not skip the remaining states.
- Candidate sync, map refresh, cleanup, preparation, repair, and election-date jobs
  share the `civic-data-writes` concurrency group with `queue: max`. Jobs wait rather
  than overwriting each other or replacing pending state-specific work. This may
  increase total elapsed collection time; scrapers currently share the writer job.

These changes prevent future invalid transitions. They do not automatically repair
historic incorrect wins, reinstate eliminated records, or validate all stored source
claims. Use retained evidence and a report-only audit before applying historical
corrections. Other data writers outside the coordinated workflows still require
application-level identity constraints/locking.

## Local verification

```sh
node --test tests/scripts/election-results.test.mjs
php artisan test tests/Feature/Console/ImportElectionResultsCommandTest.php tests/Feature/Console/CandidateDataQualityCommandsTest.php tests/Feature/Console/PoliticiansCleanupWorkflowCommandTest.php tests/Feature/Console/ReconcileMissingCandidateProfilesCommandTest.php
vendor/bin/yaml-lint .github
bash -n scripts/for-each-state.sh scripts/collect-election-results.sh scripts/audit-candidate-data.sh
```

Tests use SQLite and fake collection executables; they do not contact production.
After deployment, manually dispatch a dry run for a small set of states and inspect
source/audit artifacts. Dry-run audits intentionally fail when violations are found.
