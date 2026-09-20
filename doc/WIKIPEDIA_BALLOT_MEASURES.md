# Wikipedia ballot-measure import

```sh
php artisan ballot-measures:import-wikipedia --state=CA --year=2026 --dry-run
php artisan ballot-measures:import-wikipedia --state=CA --year=2026
php artisan ballot-measures:import-wikipedia --year=2026
```

Imports statewide measures from the per-state tables in Wikipedia's
`<year> United States ballot measures` article. No civic registry seeding or
API key is required. Omit `--state` for all configured states; omit `--year`
to use `civic.wikipedia.year` and the configured article override, if any.

The command reuses the Wikipedia adapter already available through
`civic:scrape-measures`. It saves Wikipedia attribution links, measure titles,
descriptions, yes-vote meanings, dates and recognized results. It does not
invent no-vote meanings. The existing adapter falls back to the year's general
election date for even years when a table date cannot be parsed. Review dry-run
dates before importing. Individual proposition articles and local measures
are not supported by this adapter.

Repeated imports match by state, election date, and title or measure number.
Existing non-empty fields are preserved unless `--refresh` is supplied;
original source labels are always preserved. `--dry-run` writes nothing.
No extracted measures produces a nonzero exit code and a diagnostic message;
Wikipedia availability and table layouts determine coverage.
