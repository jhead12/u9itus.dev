<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Services\PlatformSettingsService;
use Illuminate\View\View;

/**
 * Static marketing pages (How it works, Pricing).
 *
 * Pricing figures are read through PlatformSettingsService (admin override,
 * else config/u9itus.php) — the same source campaign creation charges from —
 * so the public page can't drift from what users are actually billed.
 */
class PublicPageController extends Controller
{
    public function howItWorks(): View
    {
        return view('standalone.public.how-it-works', ['pricing' => $this->rates()]);
    }

    public function pricing(): View
    {
        return view('standalone.public.pricing', ['pricing' => $this->rates()]);
    }

    /** @return array<string, float> */
    private function rates(): array
    {
        $politicianRate = (float) PlatformSettingsService::get('revenue_per_view');

        return [
            'politician_per_view'   => $politicianRate,
            'citizen_per_view'      => (float) PlatformSettingsService::get('citizen_revenue_per_view'),
            'ballot_issue_per_view' => (float) PlatformSettingsService::get('ballot_issue_revenue_per_view'),
            'viewer_payout'         => (float) PlatformSettingsService::get('viewer_payout_per_view'),
            'referral_percent'      => (float) PlatformSettingsService::get('referral_commission_percent'),
            'min_payout'            => (float) PlatformSettingsService::get('min_payout_amount'),
            // CreateCampaignRequest requires a budget of at least 10 views.
            'min_budget'            => $politicianRate * 10,
            'card_fee_percent'      => (float) config('u9itus.stripe_fee_percent'),
        ];
    }
}
