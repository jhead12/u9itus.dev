# SEO review — September 8, 2026

This started as a source-code review of the working tree, including existing local changes. The subsequent authorized implementation is summarized below. Production HTML, redirects, indexing, traffic, and Core Web Vitals could not be verified: browser fetches failed and the shell could not resolve the public hostname. That does not establish a production outage. Recommendations below distinguish original code findings from opportunities requiring measurement.

## Implemented after review

- Consolidated public-page metadata through the shared partial, wired layout sections into it, added the homepage canonical, and replaced the missing share-image fallback with the existing asset. Removed fixed dimensions for arbitrary social images.
- Added route-aware pagination canonicals. Content filters retain their query parameters and use `noindex, follow`; tracking parameters are omitted. Address lookup results and profile claim/preview pages have an explicit noindex policy.
- Encoded politician and blog structured data from PHP arrays with safe JSON serialization. Blog author type, image, and modification time now reflect the content; politician schema no longer assumes government employment.
- Shared active/published politician eligibility between public resolution and the sitemap. Added enriched PACs, scoped group URLs, published events, and Earn. Excluded empty topic archives and posts that declare a different canonical. Static URLs no longer claim a new modification time on every cache refresh.
- Stopped automatic politician slug regeneration when descriptive fields change. This prevents future broken links; it does not reconstruct historical slugs already lost before this change.
- Made the application robots route serve the same file as the web server, preserving the existing crawler preferences. Versioned sitemap and guest profile caches so old HTML/XML will not mask the update.
- Added 25 SEO regression cases. Those and 74 related blog/event/profile/group tests passed; Blade compilation and whitespace checks also passed. Local PHP commands required `-d opcache.enable_cli=0 -d opcache.file_cache=` because the configured CLI opcache directory was inaccessible.

Remaining work: deploy and inspect the live canonical host/redirects and Search Console, measure performance, build curated geographic pages, and consider additional eligible schema and sitemap partitioning as inventory grows. About/How It Works/Pricing/Contact were not added to the sitemap because the public route declarations reference missing standalone views. AI crawler access preferences were preserved.

## Existing patterns worth preserving

- Laravel/Blade renders public directories and profiles on the server. Politician cards and pagination already use ordinary links; this is a strong discovery foundation.
- Public entities have individual routes: politicians, PACs, groups, blog posts, and events. PAC profiles already redirect alternate names/IDs to the canonical slug with HTTP 301.
- Published profiles and posts have a dynamic sitemap. Guest profile HTML, transparency data, and committee queries have caches.
- There is a shared SEO partial, and politician profiles and blog posts already attempt structured data.
- The map and blog editor have separate Vite entry points. Preserve that separation.

## Prioritized findings

### 1. Make metadata have one owner — high priority

Evidence: `resources/views/standalone/partials/seo-head.blade.php:25`, `resources/views/standalone/layouts/public.blade.php:9`, `resources/views/standalone/public/profile.blade.php:9`, and `resources/views/standalone/public/politicians-directory.blade.php:9`.

The partial outputs generic descriptions, canonicals, and social tags; layouts/pages then output another set. The public layout does not pass its title/description/canonical sections into the partial. Blog posts with an explicit external canonical consequently declare both the current URL and the external URL. Profile social titles have generic and specific versions. These are competing signals, not a guaranteed ranking penalty.

Use one explicit SEO data contract, passed into the shared partial: title, description, canonical, robots, social image, and social type. Keep page-specific schema separate. A small Blade component or view-data object would fit this repo; a new SEO framework is unnecessary. Emit each tag once. Include the homepage in this pattern; its current head has no canonical link.

### 2. Preserve pagination in canonical URLs — high priority

Evidence: `resources/views/standalone/partials/seo-head.blade.php:27` uses `url()->current()`, which drops query parameters; the politician directory explicitly canonicalizes to `/politicians` while rendering paginated links at line 520. Blog archives inherit the same default.

Give page two a canonical ending in `?page=2`. Whitelist meaningful parameters rather than retaining tracking, refresh, and arbitrary search parameters. Treat pagination separately from filters: curated state/district pages can be indexable, while arbitrary search/sort combinations need an explicit crawl/index policy. Do not automatically canonicalize materially different content to an unrelated unfiltered page.

Google explicitly recommends self-canonicals for paginated pages: [pagination guidance](https://developers.google.com/search/docs/specialty/ecommerce/pagination-and-incremental-page-loading). See also [faceted navigation](https://developers.google.com/crawling/docs/faceted-navigation).

### 3. Match sitemap eligibility to public-page eligibility — high priority

Evidence: `app/Http/Controllers/Standalone/SitemapController.php:60` selects published politicians but does not require `is_active`; `PublicProfileController.php:1487` requires both for guest access. An inactive published record can therefore enter the sitemap but return 404.

Share a public/indexable query scope between sitemap generation and public controllers. Extend sitemap coverage to enriched PAC profiles, eligible public groups/events, and useful static pages such as About, How It Works, and Earn. Exclude empty topic archives and posts whose preferred canonical is elsewhere; the current post sitemap query never inspects `canonical_url`.

Static entries currently receive a fresh `lastmod` whenever the six-hour cache rebuilds, even without content changes. Use meaningful content-change timestamps or omit the field. Plan a sitemap index and separate files as inventory grows; chunked queries currently still accumulate every URL into one XML string. Verify canonical hostname consistency across `APP_URL`, redirects, and robots sitemap references in production.

Google recommends canonical URLs and accurate modification dates: [sitemap guidance](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap).

### 4. Keep profile URLs stable — high priority

Evidence: `app/Models/Politician.php:209` regenerates slugs when name, office, or city changes. `PublicProfileController.php:1487` resolves only the exact current slug; no historical-slug lookup appears in that path.

An office update can break previously shared and indexed links. Either retain slugs after publication or store historical slugs and permanently redirect them to the current profile before rendering. Test a politician changing office and requesting the former URL. The PAC controller's canonical redirect is a useful local precedent.

### 5. Repair social images and JSON-LD — medium priority

Evidence: `seo-head.blade.php:30` defaults to `public/media/og-image.png`, which is missing from this checkout. `public/images/og-default.png` exists and is used by the homepage. Use the existing asset after confirming its dimensions and production accessibility; do not claim fixed 1200×630 dimensions for arbitrary profile photos.

`resources/views/standalone/public/profile.blade.php:31` manually combines `addslashes()` and HTML escaping inside JSON. Apostrophes can produce invalid JSON escapes, and literal newlines can break JSON strings. `public/blog/show.blade.php:21` also interpolates text directly into JSON, always identifies the author as an Organization, and omits modification time and the featured image from schema.

Build PHP arrays and serialize once with JSON encoding suitable for an HTML script context, including the JSON_HEX safety flags. Test quotes, apostrophes, backslashes, newlines, and closing-script text. Use Person for human authors and Organization for actual organizations. Do not mark every candidate as working for a government organization merely because they are running for office.

Add accurate Organization/WebSite and breadcrumb markup where applicable, and Event markup for eligible public events. Keep Person markup on third-party politician dossiers; do not assume imported profiles qualify for Google's ProfilePage feature, which focuses on people affiliated with the site. [Google ProfilePage requirements](https://developers.google.com/search/docs/appearance/structured-data/profile-page).

### 6. Consolidate crawler policy — medium priority

Evidence: `public/robots.txt` and `routes/standalone.php:867` define different policies. Static-file handling determines which response is served. The static file limits OAI-SearchBot and PerplexityBot to WebMCP/API paths, blocking their access to the main public content.

Choose one source of truth. If AI-search discoverability is a product objective, review public-page access for search crawlers separately from training-crawler preferences. Preserve deliberate restrictions until that policy is decided. Treat robots exclusion as crawl control, not authentication or a reliable removal mechanism. Set explicit index/noindex rules for account, preview, search, and token-bearing utility pages where appropriate; crawlers must be allowed to fetch a page to observe its noindex directive.

## Growth opportunities using existing data

The strongest opportunity is a connected civic reference library. Suggested new routes are examples, not existing endpoints or validated keyword targets:

| Page family | Example | Useful content |
| --- | --- | --- |
| State hub | `/states/california` | Offices, upcoming elections, district links, sourced statewide context |
| District hub | `/states/california/districts/12` | Current representative, candidates by election cycle, PAC spending, events, source dates |
| Race guide | `/elections/2026/california/house/12` | Verified ballot status, candidates, deadlines, funding context |

Link Home → State → District → Candidate, with related PACs and events and links back up. Retain the interactive map as another way to explore these pages. Launch a small set with sufficiently rich, verified data before expanding; changing place names in otherwise identical text is not a useful page strategy. Preserve historical race pages and distinguish incumbents, candidates, withdrawn candidates, and election cycles.

Improve profile titles with real context: `[Name] — [Office], [State] | U9itus`; use descriptions that state what readers can actually learn. Show sources, reporting periods, last verification dates, editorial methods, and a correction path. Funding figures need clear distinctions between contributions and independent expenditures. Build explanatory articles around the same entities and link them to the underlying records. Validate topic demand with Search Console/query research before scaling content.

## Performance and verification

These are investigation targets, not measured Core Web Vitals failures:

- `routes/web.php:49` can make a geolocation request on a cold homepage visit, with a two-second timeout. Keep the primary response independent of optional personalization where practical.
- Profile transparency cache misses can still fetch multiple external providers in the request path (`PublicProfileController.php:1523`). Extend the existing background-news/committee snapshot pattern to these providers and serve the last successful snapshot.
- `resources/js/app.js` imports Flatpickr on every page; `resources/js/bootstrap.js` statically imports Echo/Pusher. Load these capabilities where needed. Measure transferred bytes and main-thread cost before and after.
- Measure mobile LCP, INP, and CLS on the homepage, directory, profile, PAC, and map pages. Inspect image dimensions/loading priority and rendering delays based on the measured bottleneck.

The blog SEO test at `tests/Feature/Blog/PostCrudTest.php:107` checks only that `og:title` exists. Add meaningful regression coverage for tag uniqueness and values, pagination canonicals, public sitemap eligibility, canonical overrides, historical URL redirects, and JSON-LD parsing. Validate generated images return 200 and sitemap URLs resolve without redirects. Keep tests isolated with mocked external services.

Suggested order: (1) metadata, canonicals, image fallback, sitemap eligibility, slug stability; (2) schema and crawler-policy consistency; (3) a small state/district pilot and measured performance improvements. After deployment, use Search Console to verify Google-selected canonicals, submitted/indexed URLs, indexing exclusions, and nonbrand impressions by page family. Compare organic profile visits and meaningful follow-on actions over time; no ranking or traffic uplift is established by this source review.
