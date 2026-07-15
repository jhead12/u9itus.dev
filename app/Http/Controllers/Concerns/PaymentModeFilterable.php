<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CampaignTransaction;
use App\Models\PoliticalCampaign;
use App\Services\StripePaymentService;

trait PaymentModeFilterable
{
    /**
     * Defaults to 'test' when the key is unrecognised so the mode filter
     * is always applied and live data is never mixed with test data.
     */
    protected function activePaymentMode(): string
    {
        $mode = app(StripePaymentService::class)->configuredMode();

        return $mode === 'live' ? 'live' : 'test';
    }

    protected function applyPaymentModeFilter($query, string $mode)
    {
        return $query->where('metadata->payment_mode', $mode);
    }

    /**
     * Campaign ids for politicians with billing activity in the active payment mode.
     *
     * Shared across the Dashboard, Analytics, Accounting, and Reports admin
     * controllers so they all scope to the currently configured Stripe mode.
     */
    protected function modeScopedCampaignIds(string $mode)
    {
        $politicianIds = CampaignTransaction::query()
            ->select('politician_id')
            ->whereNotNull('politician_id')
            ->where('metadata->payment_mode', $mode)
            ->distinct();

        return PoliticalCampaign::query()
            ->select('id')
            ->whereIn('politician_id', $politicianIds)
            ->distinct();
    }
}
