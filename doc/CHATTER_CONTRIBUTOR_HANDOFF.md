# Community chatter contributors — prompt history and recovery

## User request and agreed scope

1. User proposed a browser app for authorized users to clip social/news sources, associate them with politicians, and act as community reporters.
2. Assistant recommended a mobile-friendly link submission page and own-submission dashboard first, feeding the existing human review queue. A browser extension is a later client of this workflow.
3. User: “ok lets build it, please create a propt history just in case there is a disruption”.

This is an implementation handoff, not authorization to deploy, promote production accounts, purchase APIs, or scrape private content.

## Design / security contract

- Dedicated `chatter_contributor` role does not imply `admin` or change the user's existing portal/roles.
- Only Super Admins grant/revoke contributor membership, audited using staff access transactions.
- Existing active, verified users only. Contributor routes recheck suspension and membership each request; normal authentication/2FA policies still apply.
- Contributors submit source URL, politician, platform, suggested headline, relevance and optional explicitly selected excerpt. No automatic page fetch, credentials, browser history, uploads or social API access.
- All submissions start pending/unverified. Ignore forged moderation, reviewer, publication, ownership and internal-note fields.
- Contributors see only their own submission history, without editorial notes or reviewer audit history. Editors use existing chatter review queue. No contributor edit/publish endpoints.
- Validate public HTTP(S) URLs, bound text lengths, throttle submissions, retain database duplicate constraint. Store submission and audit atomically.
- Contributor-supplied context/excerpts are private editorial input; only editor-reviewed public headline/summary can be published.
- Reuse the searchable candidate picker, including a plain-select fallback.

## Work ledger

- [x] Inspected existing chatter model/controller, staff role assignment, routes and schema.
- [x] Add contributor schema and role installer/migration.
- [x] Add owner-controlled contributor grant/revoke UI and endpoint.
- [x] Build authenticated contributor form, own history and submission handler.
- [x] Surface contributor provenance/private context to editors.
- [x] Add security/regression tests and run them (final results below).
- [x] Record verification and deployment steps here.

## Implemented entry points

- `/contribute/chatter`: authenticated form + paginated own-submission history; POST throttled at 10/minute per authenticated user.
- Admin → Staff access → search existing account → separate **Save contributor access** form. This does not promote the account to admin. Clearing contributor access does not revoke independent staff permissions.
- Existing admin chatter review shows private contributor input under “Edit evidence and review history”. Publication requires a nonempty public summary. Contributor POST deliberately ignores summary, moderation, reviewer and ownership fields.
- Attribution is nullable on account deletion. Excerpts/notes/submitter ID are hidden from model serialization. Audit log uses its existing `admin_user_id` actor column even for contributor submissions; distinguish them by `contributor_submitted` action.
- The shared candidate picker is `resources/views/standalone/partials/chatter-candidate-picker.blade.php`; both admin and contributor forms reuse it.

## Verification / known limits

- Final combined suite: **28 passed, 352 assertions**. Command: `php artisan test tests/Feature/Standalone/ChatterContributorTest.php tests/Feature/Standalone/PoliticianChatterReviewTest.php tests/Feature/Standalone/StaffPermissionsTest.php tests/Feature/Auth/AdminTwoFactorPolicyTest.php`. Includes 9 contributor tests covering forged fields, own-only history, anonymous/unapproved/suspended/unverified/revoked access, consent, public URL checks, duplicates, owner grants, editorial publication gate, public-profile privacy, throttle, 2FA, guest denial, and audit rollback.
- Candidate-picker browser test passed: `node --test tests/browser/chatter-politician-picker.test.mjs`.
- `npm run build` passed; warnings about mixed static/dynamic imports and large bundles remain outside this feature's scope. `git diff --check` passed. No production migration executed.
- One earlier combined test run transiently could not find new routes despite their presence on disk; both the independent rerun and final combined suite passed. No route-cache workaround was needed.
- Duplicate detection is exact URL + politician using the existing database unique constraint; tracking-parameter/canonical URL variants are not deduplicated yet.
- URL validation rejects obvious private/local IPs and embedded credentials. No remote fetching or DNS resolution occurs; it cannot certify that a site is public or its contents accurate. User confirmation and editorial review remain required.
- No browser extension, API token flow, scraping, uploads, notification emails, contributor edit/withdraw flow, or public contributor attribution in this phase.
- Full-page visual/mobile QA is not yet performed; automated tests cover rendered pages and browser interactions for the picker.

## Deployment / operator checklist

No production writes, grants, commits, or deployments were performed in this task. Current checkout at start: `feature/white-label-portal-builder`; do not switch branches or assume unrelated work is part of this feature.

1. Review and commit the scoped changes; deploy through the normal application pipeline including frontend asset build.
2. Confirm the earlier chatter and staff audit migrations are already applied; then apply `database/migrations/2026_09_23_000001_add_chatter_contributors.php` to the intended environment. For linked Railway: `railway run php artisan migrate --path=database/migrations/2026_09_23_000001_add_chatter_contributors.php --force`.
3. Refresh normal deployment route/config/view caches after deploying the new routes. Do not run destructive migration resets. The down migration removes private input/attribution, so prefer a forward fix if live submissions exist.
4. As Super Admin, use the separate contributor access form to approve a verified existing account. Share `/contribute/chatter`; authorized accounts also see a navigation link.
5. Smoke-test on desktop and mobile: contributor cannot enter admin, a submitted source appears pending to editors, another contributor cannot see it, editor writes neutral public context and publishes, private input never appears on the public profile, revoke access and verify immediate denial.

## Resume prompt

Read this file, git status, and applicable AGENTS.md before editing. Preserve other work. Inspect existing implementation before repeating any step. Complete unchecked items; update this ledger with exact tests and failures. Do not claim extension support, deployments, commits, or production access grants unless actually completed. No Supabase is used: this is Laravel/Eloquent with Spatie roles. Keep contributor access separate from admin membership. Test anonymous/unverified/suspended/revoked access, forged publication fields, own-only history, duplicate links, rate limits, grants and audit persistence. Review migrations before suggesting production rollout.

## Follow-up (not part of initial build)

Browser extension: user-triggered URL/title/selected-text capture only, least-privilege browser permissions, explicit confirmation, authenticated submission transport and no private-page collection. Also consider editorial feedback, abuse reporting and URL canonicalization. Do not introduce external scraping/API integrations without a separate request.
