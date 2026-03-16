# Local Candidate Integration System

## Overview

The U9itus platform now supports multi-source local candidate data aggregation. This system combines data from:

1. **Database** (manually imported candidates)
2. **Google Civic API** (free, official representatives by address)
3. **Ballotpedia** (comprehensive election data, free tier available)
4. **VoteSmart** (voting records and candidate profiles)
5. **State & Federal APIs** (FEC, OpenSecrets, state-specific)

## API Setup

### 1. Google Civic API (Recommended for Local)

**Why**: Free tier covers all 50 states + 3500+ local jurisdictions. Perfect for address-based local official lookup.

**Setup**:
```bash
# 1. Go to https://console.cloud.google.com/apis/credentials
# 2. Create a new API key
# 3. Enable "Civic Information API" in your project
# 4. Add to .env:
GOOGLE_CIVIC_API_KEY=your_api_key_here

# 5. Test the connection:
php artisan candidates:search-local --address="123 Main St, Austin, TX 78701"
```

### 2. Ballotpedia API

**Why**: Covers federal, state, and local elections. Supports local candidate search.

**Setup**:
```bash
# 1. Register at https://ballotpedia.org/api/request
# 2. Get your API key
# 3. Add to .env:
BALLOTPEDIA_API_KEY=your_api_key_here
```

### 3. VoteSmart API

**Why**: Free voting records and candidate profiles. Supports all election levels.

**Setup**:
```bash
# 1. Register at https://justfacts.votesmart.org/api/register
# 2. Get your API key
# 3. Add to .env:
VOTESMART_API_KEY=your_api_key_here
```

### 4. Federal Election Commission (FEC) API

**Why**: Campaign finance data for federal candidates.

**Setup**:
```bash
# 1. Register at https://api.open.fec.gov/developers
# 2. FEC APIs are usually free and don't require registration
# 3. Add to .env:
FEC_API_KEY=your_api_key_here
```

## Usage

### Via Artisan Command

```bash
# Search by address (tries all configured sources)
php artisan candidates:search-local --address="123 Main St, Austin, TX"

# Search by city and state
php artisan candidates:search-local --city="Austin" --state="TX"

# Search by state with governance level filters
php artisan candidates:search-local --state="TX" --governance-levels="City" --governance-levels="County"

# Exclude federal candidates (local only)
php artisan candidates:search-local --address="123 Main St, Austin, TX" --exclude-federal

# Limit results
php artisan candidates:search-local --address="123 Main St, Austin, TX" --limit=10
```

### Via PHP Code

```php
use App\Services\LocalCandidateAggregator;

$aggregator = app(LocalCandidateAggregator::class);

// By address
$candidates = $aggregator->findByAddress('123 Main St, Austin, TX', [
    'exclude_federal' => true,
    'governance_levels' => ['City', 'County'],
]);

// By city and state
$candidates = $aggregator->findByCity('Austin', 'TX');

// By state
$candidates = $aggregator->findByState('TX', ['City', 'County']);

// Iterate results
foreach ($candidates as $candidate) {
    echo $candidate['full_name'];
    echo $candidate['political_office'];
    echo $candidate['governance_level'];
    echo $candidate['source']; // 'database', 'google_civic', 'ballotpedia', etc.
}
```

### In Controllers

```php
namespace App\Http\Controllers\Standalone;

use App\Services\LocalCandidateAggregator;

class CandidateController extends Controller
{
    public function searchLocal(Request $request, LocalCandidateAggregator $aggregator)
    {
        $validated = $request->validate([
            'address' => 'required|string',
            'governance_levels' => 'nullable|array',
        ]);

        $candidates = $aggregator->findByAddress(
            $validated['address'],
            ['governance_levels' => $validated['governance_levels'] ?? []]
        );

        return response()->json([
            'count' => $candidates->count(),
            'candidates' => $candidates,
        ]);
    }
}
```

## Data Structure

Each candidate record includes:

```php
[
    'external_candidate_id' => 'unique_id_from_source',
    'full_name' => 'John Doe',
    'political_office' => 'City Council Member',
    'governance_level' => 'City',  // Federal, State, County, City, School Board, Judicial
    'state' => 'TX',
    'city' => 'Austin',
    'district' => 'District 4',
    'party_affiliation' => 'Democrat',
    'phone' => '+1-512-555-0123',
    'email' => 'john@example.com',
    'website' => 'https://johndoe.com',
    'photo_url' => 'https://...',
    'source' => 'google_civic',  // database, google_civic, ballotpedia, votesmart
]
```

## Source Priority

When searching, the system tries sources in this order:

1. **Database** — User-uploaded candidates (highest priority)
2. **Google Civic** — Current office holders by address
3. **Ballotpedia** — Upcoming elections
4. **VoteSmart** — Voting records & fallback
5. **State APIs** — State-specific records (if implemented)

Results are automatically deduplicated by name + office + governance level.

## Integration with District Lookup

The district lookup flow can now be enhanced to show local candidates:

```php
// Old: Only federal candidates
$candidates = Politician::where('state', $state)->where('governance_level', 'Federal')->get();

// New: Mix federal and local
$publicController = app(PublicProfileController::class);
$civicAggregator = app(LocalCandidateAggregator::class);

// Get both federal and local
$candidates = collect();
$candidates = $candidates->merge(Politician::where('state', $state)->get());
$candidates = $candidates->merge(
    $civicAggregator->findByState($state, ['City', 'County', 'State'])
);
```

## Caching

All API responses are cached to minimize external requests:

- **Google Civic**: 7 days (official info rarely changes)
- **Ballotpedia**: 24 hours
- **Database**: No cache (always fresh)

Clear cache when needed:

```bash
php artisan cache:clear
```

## Error Handling

All services degrade gracefully:

- If Google Civic API key is missing, system skips it
- If API request fails, system logs warning and tries next source
- If all sources fail, database-only results are returned
- No errors are thrown to end users

Check logs for API issues:

```bash
tail storage/logs/laravel.log | grep -i candidate
tail storage/logs/laravel.log | grep -i civic
```

## Next Steps

1. **Set API keys** in `.env` for the sources you want to use
2. **Import local candidates** manually via admin UI if desired
3. **Test** the command: `php artisan candidates:search-local --address="..."`
4. **Integrate** into your district lookup view to show local candidates
5. **Monitor** logs for any API integration issues

## Troubleshooting

### "No API key configured" warning

Set the missing API key in `.env`. All are optional — system works without them.

### Results are only from database

Make sure API keys are set in `.env` and the services are configured in `config/services.php`.

### Cached results are stale

Run `php artisan cache:clear` to refresh all cached data.

### Address parsing not working

Use full addresses with city and state (e.g., "123 Main St, Austin, TX 78701").

## Architecture

```
LocalCandidateAggregator (main orchestrator)
├── GoogleCivicService (local officials by address)
├── BallotpediaService (enhanced with local filtering)
├── VoteSmartService (voting records)
├── ElectionCandidateRecord model (database)
└── SearchLocalCandidates command (CLI tool)
```

The aggregator chains these services together, deduplicates results, and applies filters.
