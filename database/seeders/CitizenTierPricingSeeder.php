<?php

namespace Database\Seeders;

use App\Services\PlatformSettingsService;
use Illuminate\Database\Seeder;

/**
 * Seeds platform_settings pricing rows for the two Citizen pricing tiers
 * (per doc/Creative.md Sprint 7.5):
 *
 *   citizen        Standard citizen ads (local_business, community_notice,
 *                  general_announcement) — $0.75/view, $0.50 voter payout.
 *   ballot_issue   Ballot-issue campaigns — $1.00/view, $0.50 voter payout,
 *                  same rate as political campaigns, always admin-reviewed.
 *
 * NOTE: platform_settings.key has a standalone unique index (not composite
 * with user_tier — confirmed: only the original create-table migration ever
 * touches this table), so two tiers cannot share one key via the
 * key + user_tier lookup pattern. To avoid altering the shared production
 * schema, each tier gets its own namespaced key instead of reusing
 * 'revenue_per_view' with a user_tier override. CitizenCampaign::boot()
 * reads these same namespaced keys.
 */
class CitizenTierPricingSeeder extends Seeder
{
    public function run(): void
    {
        PlatformSettingsService::set('citizen_revenue_per_view', 0.75, [
            'description' => 'Citizen standard tier — cost per view',
            'category'    => 'pricing',
        ]);

        PlatformSettingsService::set('citizen_voter_payout_per_view', 0.50, [
            'description' => 'Citizen standard tier — voter payout per view',
            'category'    => 'pricing',
        ]);

        PlatformSettingsService::set('ballot_issue_revenue_per_view', 1.00, [
            'description' => 'Citizen ballot-issue tier — cost per view',
            'category'    => 'pricing',
        ]);

        PlatformSettingsService::set('ballot_issue_voter_payout_per_view', 0.50, [
            'description' => 'Citizen ballot-issue tier — voter payout per view',
            'category'    => 'pricing',
        ]);
    }
}
