-- ============================================================
-- One-off account removal for j.eldonmusic@gmail.com
-- Run inside a transaction so it is fully atomic.
-- Test on a DB backup before running in production.
-- ============================================================

START TRANSACTION;

-- Step 1: Capture user info
SET @target_email = 'j.eldonmusic@gmail.com';

SELECT id INTO @user_id   FROM users   WHERE email = @target_email LIMIT 1;
SELECT id INTO @voter_id  FROM voters  WHERE user_id = @user_id    LIMIT 1;
SELECT id INTO @pol_id    FROM politicians WHERE user_id = @user_id LIMIT 1;
SELECT id INTO @cit_id    FROM citizens WHERE user_id = @user_id   LIMIT 1;

-- Abort if the user does not exist
SELECT IF(@user_id IS NULL, (SELECT 'ERROR: user not found' UNION SELECT 1/0), 'OK') AS preflight_check;

-- Step 2: Archive to deleted_accounts
INSERT INTO deleted_accounts (
    original_user_id,
    email,
    first_name,
    last_name,
    user_type,
    platform,
    registration_ip,
    voter_id,
    politician_id,
    citizen_id,
    user_snapshot,
    deleted_by_user_id,
    deletion_reason,
    deleted_by_ip,
    deleted_at,
    created_at,
    updated_at
)
SELECT
    id,
    email,
    first_name,
    last_name,
    user_type,
    platform,
    registration_ip,
    @voter_id,
    @pol_id,
    @cit_id,
    JSON_OBJECT(
        'id',                  id,
        'name',                name,
        'email',               email,
        'first_name',          first_name,
        'last_name',           last_name,
        'user_type',           user_type,
        'platform',            platform,
        'phone',               phone,
        'phone_verified_at',   phone_verified_at,
        'kyc_status',          kyc_status,
        'street_address',      street_address,
        'city',                city,
        'state',               state,
        'zip_code',            zip_code,
        'country',             country,
        'is_verified',         is_verified,
        'idme_uuid',           idme_uuid,
        'idme_verified_at',    idme_verified_at,
        'registration_ip',     registration_ip,
        'suspended_at',        suspended_at,
        'suspension_reason',   suspension_reason,
        'kyc_status',          kyc_status,
        'kyc_reviewed_at',     kyc_reviewed_at,
        'kyc_rejection_reason',kyc_rejection_reason,
        'email_verified_at',   email_verified_at,
        'created_at',          created_at
    ),
    NULL,   -- deleted_by_user_id (manual SQL run, no admin)
    'Manual removal via SQL script — requested account purge',
    NULL,   -- deleted_by_ip
    NOW(),
    NOW(),
    NOW()
FROM users
WHERE id = @user_id;

-- Step 3: Delete voter profile (cascades: view_sessions, ad_view_tokens,
--         fraud_signals, referral_earnings, engagement_survey_responses,
--         voter_watch_reports, voter_favorite_politicians, etc.)
DELETE FROM voters WHERE id = @voter_id;

-- Step 4: Delete politician profile if one exists (cascades: political_campaigns,
--         politician_credits, politician_pages, politician_topics, etc.)
DELETE FROM politicians WHERE id = @pol_id;

-- Step 5: Delete citizen profile if one exists (cascades: citizen_campaigns)
DELETE FROM citizens WHERE id = @cit_id;

-- Step 6: Delete user row (cascades: user_onboarding_progress,
--         notification_preferences, onboarding_handoff_events,
--         phone_verification_codes, campaign_audit_logs, etc.)
DELETE FROM users WHERE id = @user_id;

-- Step 7: Clean up email-keyed tables with no FK constraint
DELETE FROM password_reset_tokens     WHERE email = @target_email;
DELETE FROM mailing_list_subscribers  WHERE email = @target_email;
DELETE FROM registration_attempts     WHERE email = @target_email;

-- Step 8: Verify
SELECT
    (SELECT COUNT(*) FROM users          WHERE email = @target_email) AS users_remaining,
    (SELECT COUNT(*) FROM voters         WHERE id = @voter_id)        AS voter_remaining,
    (SELECT COUNT(*) FROM deleted_accounts WHERE email = @target_email) AS archive_rows;
-- Expected: users_remaining=0, voter_remaining=0, archive_rows=1

COMMIT;
-- If anything looked wrong above, run ROLLBACK; instead of COMMIT;
