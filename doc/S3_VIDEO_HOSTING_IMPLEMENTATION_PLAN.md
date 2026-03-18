# S3 Video Hosting Implementation Plan

## Goal

Implement production-grade S3-based campaign video hosting in the current Laravel app while preserving existing watch tracking, anti-skip behavior, and payout logic.

## Current State (Verified)

- Upload endpoint exists in app/Http/Controllers/Standalone/PoliticianController.php and stores files to the default filesystem disk via uploadVideo().
- S3 disk is already configured in config/filesystems.php.
- Voter playback view exists in resources/views/standalone/voter/watch.blade.php and currently uses campaign.media_url directly.
- Routes already support upload and watch flows in routes/standalone.php.

## Non-Goals (Phase 1)

- No full transcoding pipeline yet (HLS/DASH can be phase 2).
- No Cloudflare Stream migration in phase 1.
- No major UI redesign.

## Architecture Decisions

Choose one delivery mode before coding:

1. Public object mode (fastest)
- Store S3 object keys and serve via public bucket/CloudFront URL.
- Lower backend complexity, weaker object-level control.

2. Private object mode with signed URLs (recommended)
- Keep bucket private, generate short-lived signed URLs on watch page/API.
- Better security and abuse resistance.

Recommended: option 2 with 10-minute signed URL TTL.

## Phase Plan

## Phase 0 - Decision and Environment Hardening

Tasks:
- Finalize private bucket + signed URL approach.
- Add environment variables for S3 and delivery behavior.
- Confirm IAM policy allows put/get/delete/list only for campaigns/* prefix.

Files:
- .env.example
- config/filesystems.php
- config/services.php (if adding media config section)

Acceptance Criteria:
- Local and staging can upload to S3 using FILESYSTEM_DISK=s3.
- No hardcoded credentials in repo.

## Phase 1 - Normalize Stored Media Reference

Problem:
- Current implementation persists full media_url. This is brittle for disk migrations and delete operations.

Tasks:
- Add media_path column to political_campaigns.
- Keep media_url temporarily for backward compatibility.
- Persist S3 object key in media_path.

Files:
- database/migrations/*_add_media_path_to_political_campaigns_table.php
- app/Models/PoliticalCampaign.php
- app/Http/Controllers/Standalone/PoliticianController.php

Acceptance Criteria:
- New uploads save media_path.
- Existing records still play from media_url.

## Phase 2 - Introduce Media URL Resolver Service

Tasks:
- Create service that resolves playable URL from campaign:
  - If media_path exists and disk is private S3: return temporary signed URL.
  - If media_path exists and disk is public: return Storage::url().
  - Fallback to legacy media_url.
- Centralize URL generation logic to avoid duplicating in controllers/views.

Files:
- app/Services/CampaignMediaService.php (new)
- app/Http/Controllers/Standalone/VoterController.php
- app/Http/Controllers/Api/VoterController.php
- resources/views/standalone/voter/watch.blade.php

Acceptance Criteria:
- Watch page works for both legacy and new campaigns.
- Signed URL expires and cannot be reused long-term.

## Phase 3 - Refactor Upload/Delete Flow

Tasks:
- In uploadVideo(), store video using a deterministic key pattern:
  - campaigns/{campaign_id}/video/{uuid-or-timestamp}.mp4
- Save media_path; optionally save media_url only for legacy compatibility.
- Delete old object by stored media_path (not URL parsing).
- Keep ffprobe duration validation in place.

Files:
- app/Http/Controllers/Standalone/PoliticianController.php
- tests/Feature/PoliticianCampaignUploadTest.php (new or updated)

Acceptance Criteria:
- Re-upload replaces previous object without orphan files.
- Duration checks still enforced.

## Phase 4 - Security and Playback Hardening

Tasks:
- Set signed URL TTL to short window (example 10 minutes).
- Regenerate URL per watch request.
- Ensure watch authorization remains token-gated before resolving playback URL.
- Add object metadata and content-type validation on upload.

Files:
- app/Services/CampaignMediaService.php
- app/Http/Controllers/Standalone/VoterController.php
- app/Http/Controllers/Api/VoterController.php

Acceptance Criteria:
- Direct S3 link sharing outside TTL fails.
- Authorized viewer can still watch seamlessly.

## Phase 5 - Backfill and Data Migration

Tasks:
- Build one-time artisan command to backfill media_path from existing media_url when possible.
- Add report output for records that cannot be auto-mapped.
- Run in dry-run mode first, then execute in staging, then production.

Files:
- app/Console/Commands/BackfillCampaignMediaPath.php (new)
- routes/console.php

Acceptance Criteria:
- >=95% legacy records auto-mapped.
- Remaining records listed for manual fix.

## Phase 6 - Testing, Observability, and Rollout

Tasks:
- Add feature tests for upload, watch URL resolution, and signed URL behavior.
- Add logs/metrics for upload failures and signed URL generation failures.
- Roll out via feature flag or env toggle:
  - MEDIA_USE_SIGNED_URLS=true

Files:
- tests/Feature/*campaign* or tests/Feature/*voter*
- config/u9itus.php or config/platform.php (toggle)

Acceptance Criteria:
- All tests pass in CI.
- Staging soak test shows no payout regression.

## Suggested Task Breakdown (1-Week)

Day 1:
- Phase 0 and Phase 1 migration/model updates.

Day 2:
- Phase 2 resolver service + watch integration.

Day 3:
- Phase 3 upload/delete refactor + tests.

Day 4:
- Phase 4 signed URL hardening and validation.

Day 5:
- Phase 5 backfill command + staging dry run.

Day 6-7:
- Phase 6 test hardening, monitoring, production rollout.

## Risk Register

- Risk: Broken playback for legacy media_url records.
- Mitigation: Resolver fallback path + backfill command + staged rollout.

- Risk: Orphaned objects in S3 after re-uploads.
- Mitigation: Delete by media_path and add periodic cleanup job.

- Risk: Signed URL expiration interrupts playback on slow start.
- Mitigation: Use 10-minute TTL and mint URL only after user enters watch flow.

## Definition of Done

- New campaign uploads store media_path on S3.
- Voter watch flow uses resolved URL from service, signed when private mode enabled.
- Legacy records remain playable.
- Upload, watch, and payout flows pass feature tests.
- Documentation updated in README implementation progress and deployment section.
