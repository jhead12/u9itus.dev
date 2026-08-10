<?php

namespace Database\Seeders;

use App\Services\PlatformSettingsService;
use Illuminate\Database\Seeder;

/**
 * Seeds platform_settings rows for every remaining key in
 * PlatformSettingsService::mapKeyToConfig() that didn't already have an
 * active DB row (citizen/ballot-issue pricing, max_video_duration, and
 * admin_2fa_enforced were seeded elsewhere).
 *
 * Values are copied 1:1 from each key's current config()-resolved default
 * (config/u9itus.php + .env) so running this seeder is behavior-neutral —
 * it only moves the source of truth from config into the DB so admins can
 * adjust these from the Platform Settings panel without a deploy.
 */
class PlatformSettingsDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        // Pricing
        PlatformSettingsService::set('revenue_per_view', 1.00, [
            'description' => 'Politician campaign — cost per verified view',
            'category'    => 'pricing',
        ]);

        PlatformSettingsService::set('viewer_payout_per_view', 0.50, [
            'description' => 'Voter payout per qualifying view',
            'category'    => 'pricing',
        ]);

        PlatformSettingsService::set('referral_commission_percent', 10, [
            'description' => 'Referral commission, percent of referred view payouts',
            'category'    => 'pricing',
        ]);

        PlatformSettingsService::set('procurement_commission_percent', 10, [
            'description' => 'Procurement commission percent',
            'category'    => 'pricing',
        ]);

        PlatformSettingsService::set('batch_payout_min', 25.00, [
            'description' => 'Minimum balance required to trigger a batch payout',
            'category'    => 'pricing',
        ]);

        PlatformSettingsService::set('min_payout_amount', 25.00, [
            'description' => 'Minimum balance a voter can manually cash out',
            'category'    => 'pricing',
        ]);

        // Video
        PlatformSettingsService::set('min_video_duration', 10, [
            'description' => 'Minimum campaign video length voters must watch (seconds)',
            'category'    => 'video',
        ]);

        PlatformSettingsService::set('max_video_size_mb', 1024, [
            'description' => 'Maximum campaign video upload size (MB)',
            'category'    => 'video',
        ]);

        PlatformSettingsService::set('min_watch_time_percent', 80, [
            'description' => 'Minimum percent of a video a voter must watch for the view to qualify',
            'category'    => 'video',
        ]);

        PlatformSettingsService::set('video_subtitles_enabled', false, [
            'description' => 'Render per-campaign WebVTT subtitle tracks on the voter watch player',
            'category'    => 'video',
        ]);

        // Fraud
        PlatformSettingsService::set('fraud_max_views_per_day', 50, [
            'description' => 'Max views a single voter may log per day before flagging',
            'category'    => 'fraud',
        ]);

        PlatformSettingsService::set('fraud_payout_hold_hours', 48, [
            'description' => 'Hours a payout is held before release, for fraud review',
            'category'    => 'fraud',
        ]);

        PlatformSettingsService::set('fraud_auto_flag_threshold', 80, [
            'description' => 'Cumulative fraud score at which a voter is auto-flagged',
            'category'    => 'fraud',
        ]);

        PlatformSettingsService::set('fraud_suspicious_threshold', 10, [
            'description' => 'Activity score at which a voter is marked suspicious',
            'category'    => 'fraud',
        ]);

        // Other
        PlatformSettingsService::set('assignment_expiry_hours', 24, [
            'description' => 'Hours before an unclaimed campaign assignment expires',
            'category'    => 'general',
        ]);

        PlatformSettingsService::set('head_enterprises_fee_percent', 15.0, [
            'description' => 'Platform fee percent retained by Head Enterprises',
            'category'    => 'general',
        ]);
    }
}
