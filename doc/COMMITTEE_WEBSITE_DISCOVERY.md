# Committee website discovery

Run migrations before using the command:

```sh
php artisan migrate --force
php artisan committees:discover-websites --committee=C00707844 --dry-run
php artisan committees:discover-websites --committee=C00707844
php artisan committees:discover-websites --limit=100
```

`--committee` accepts a FEC ID, PAC slug, or full PAC page URL. The committee
must already exist in the registry; use `committees:enrich-profiles` to import
missing committee records and populate their public profiles.

Discovery checks manually verified mappings in `config/committee_websites.php`,
then the cached FEC website (or the FEC API when `FEC_API_KEY` is configured),
then explicitly labelled official-site links on the committee's Ballotpedia page.
This is reference-based discovery, not a general web search; some committees will
remain unresolved. EDF Action Votes has a verified mapping based on its site's
committee disclosure. Add further verified mappings by exact FEC ID with a source URL.

The command prints each discovery and its source, skips existing discoveries,
and supports `--force` to recheck them. A failed or empty lookup never clears an
existing website. `--dry-run` performs lookups without database writes. Failures
are reported per committee; a run with failures exits nonzero.

Discovered websites, source URLs, and discovery times are saved on the committee
registry separately from FEC profile data, so financial enrichment cannot erase
them. Public PAC profiles prefer this link over the filed website. Linked parent
organization websites remain separate. The command is manual, not scheduled.
