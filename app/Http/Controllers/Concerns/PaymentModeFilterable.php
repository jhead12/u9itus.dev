<?php

namespace App\Http\Controllers\Concerns;

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
}
