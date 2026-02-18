<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseCreditsRequest;
use App\Models\Politician;
use App\Services\CampaignBillingService;
use Illuminate\Http\JsonResponse;

class BillingController extends Controller
{
    protected CampaignBillingService $billing;

    public function __construct(CampaignBillingService $billing)
    {
        $this->billing = $billing;
    }

    public function balance(Politician $politician): JsonResponse
    {
        $balance = \App\Models\PoliticianCredit::where('politician_id', $politician->id)
            ->orderBy('created_at', 'desc')
            ->value('balance_after') ?: 0.00;

        return response()->json(['balance' => (float) $balance]);
    }

    public function purchase(PurchaseCreditsRequest $request, Politician $politician): JsonResponse
    {
        $amount = (float) $request->input('amount');

        $result = $this->billing->createPurchaseIntent($politician, $amount, []);

        if (empty($result['payment_intent_id'])) {
            return response()->json(['error' => 'Unable to create payment intent'], 500);
        }

        return response()->json([
            'payment_intent_id' => $result['payment_intent_id'],
            'client_secret' => $result['client_secret'] ?? null,
        ]);
    }
}
