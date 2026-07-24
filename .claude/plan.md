# Citizen Campaign Voter Preview / Review System

## Goal
Let a citizen preview their draft citizen campaign exactly as a voter will see it before they submit it for admin review. This reduces rejections, builds trust, and matches the politician campaign preview pattern already in the codebase.

## Current state

- `resources/views/standalone/citizen/campaigns/show.blade.php` shows campaign details but **has no video preview or voter-facing review step**.
- `resources/views/standalone/politician/campaigns/show.blade.php` already has a **Video Preview Panel** with a test-play button and payout timer simulation — this is the pattern to mirror.
- `resources/views/standalone/voter/watch-citizen.blade.php` is the real voter watch page (player, earn banner, completion post), but it only allows `status=active && approval_status=approved` campaigns.
- Citizen campaigns move: `draft` → `submitForReview()` → `pending_approval` → admin approve → `active`.
- The citizen show page already has a **Submit for Review** button, but there is no explicit review/preview step before submission.

## Proposed approach (recommended)

Build a **two-part preview experience**:

1. **Inline video preview on the citizen campaign detail page** (mirrors politician pattern).
   - Shows the actual video player (YouTube / native / Vimeo) when `media_url` is present.
   - Displays a simulated payout timer so the citizen sees when a voter becomes eligible.
   - Has a **“Review as Voter”** button that opens the full voter preview.

2. **Dedicated “Review as Voter” page** (`GET /citizen/campaigns/{campaign}/review`).
   - Renders a voter-like view using the same player markup and styling as `watch-citizen.blade.php`.
   - Runs in **preview mode**: no completion post, no payout credit, no view counter increment, no daily-limit check.
   - Shows a persistent **“Preview Mode”** banner with:
     - **Back to campaign** link
     - **Edit campaign** link (draft only)
     - **Submit for Review** form button (draft only)
   - Blocked unless the campaign is `draft` or `cancelled` and owned by the current citizen.

## Why this approach

- **Familiar to users**: the politician portal already previews campaigns the same way.
- **Reuses existing code**: the review page can reuse the same player-detection/JS patterns from `watch-citizen.blade.php` without changing the voter controller.
- **Safe**: preview mode never mutates view sessions, payouts, or counters, and it keeps draft campaigns inaccessible to real voters.
- **Clear submission flow**: citizen sees the voter experience, then explicitly submits.

## Files to change

### Backend

1. `routes/standalone.php`
   - Add `GET /citizen/campaigns/{campaign}/review` → `CitizenController@reviewCampaign` (name: `citizen.campaigns.review`).

2. `app/Http/Controllers/Standalone/CitizenController.php`
   - Add `reviewCampaign(CitizenCampaign $campaign)` method:
     - Authorize ownership.
     - Restrict to `draft` or `cancelled`.
     - Require `media_url` or `live_feed_url`; otherwise redirect back with an error.
     - Compute `$duration`, `$mustWatch`, `$payout` exactly like `CitizenCampaignVoterController::watch()`.
     - Return view `standalone.citizen.campaigns.review` with `preview => true`.

### Frontend

3. `resources/views/standalone/citizen/campaigns/show.blade.php`
   - Add a **Video Preview Panel** when `media_url` exists (copy/adapt politician show-page preview markup).
   - Replace the current lone **Submit for Review** button with a two-step action:
     - **Review as Voter** button/link that goes to the new review route.
     - On the review page, the citizen can then submit.

4. `resources/views/standalone/citizen/campaigns/review.blade.php` (new)
   - Extends `standalone.layouts.dashboard`.
   - Top banner: “Preview Mode — you are viewing this campaign as a voter will see it.”
   - Main content mirrors `standalone.voter.watch-citizen.blade.php`:
     - Campaign header with sponsor name.
     - Earn banner with `$payout` and `mustWatch%`.
     - Video player (YouTube/Vimeo/native) with play overlay.
     - Progress bar (visual only).
   - Player JS is copied/adapted from `watch-citizen.blade.php` but **without** the `completeUrl` fetch / `handleCompletion()` call.
   - Bottom action bar:
     - **Back to Campaign** → `citizen.campaigns.show`
     - **Edit Campaign** → `citizen.campaigns.edit` (draft only)
     - **Submit for Review** → POST `citizen.campaigns.submit-review`

### Tests

5. `tests/Feature/Citizen/CitizenCampaignCrudTest.php`
   - Add tests for the new `citizen.campaigns.review` route:
     - Owner can access review page for a draft campaign with video.
     - Non-owner gets 403.
     - Non-draft campaign gets 403.
     - Campaign without video/live feed redirects with error.
   - Add a test that the review page contains expected preview-mode text and does not increment `views_completed` or create a `CitizenViewSession`.

## Alternative considered

- **Open the real voter watch page in preview mode**: reuse `voter.citizen-campaigns.watch` with a `preview` flag. Rejected because it would require adding ownership/citizen exceptions to the voter controller and the voter middleware stack, muddying the separation between citizen and voter portals.

## Open questions

1. Should live-feed campaigns also be previewable? (The review page can show the live feed URL and scheduled time, but cannot play a non-active stream.)
2. Should the citizen be able to leave a self-note/confirmation checklist before submitting (e.g., “I confirm the video is clear and the targeting is correct”)?
3. Should the review page enforce the same ZIP/radius eligibility check so the citizen sees whether they themselves would be eligible, or simply show the chosen targeting?

The implementation below will assume answers: **(1) yes, with a text placeholder for the live feed; (2) no checklist for now — just preview + submit; (3) show targeting only, no eligibility gate.** If you want any of these changed, say so before I implement.
