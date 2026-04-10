<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayPalWebhookEvent;
use App\Services\PayPalPayoutReconciliationService;
use App\Services\PayPalPayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayPalWebhookController extends Controller
{
    public function handle(
        Request $request,
        PayPalPayoutService $paypalService,
        PayPalPayoutReconciliationService $reconciliationService
    ) {
        $payload = $request->json()->all();
        $eventId = (string) ($payload['id'] ?? '');
        $eventType = (string) ($payload['event_type'] ?? 'unknown');

        $strictVerification = (bool) config('services.paypal.strict_webhook_verification', app()->environment('production'));
        if ($strictVerification && ! $paypalService->verifyWebhookSignature($request, $payload)) {
            return response()->json(['error' => 'Invalid webhook signature'], 400);
        }

        if ($eventId !== '' && PayPalWebhookEvent::query()->where('paypal_event_id', $eventId)->exists()) {
            return response()->json(['status' => 'duplicate']);
        }

        $resource = (array) ($payload['resource'] ?? []);
        $batchReference = (string) ($resource['payout_batch_id']
            ?? $resource['sender_batch_header']['sender_batch_id']
            ?? $resource['sender_item_id']
            ?? '');

        $event = PayPalWebhookEvent::create([
            'paypal_event_id' => $eventId !== '' ? $eventId : uniqid('paypal_evt_', true),
            'event_type' => $eventType,
            'resource_reference' => $batchReference !== '' ? $batchReference : null,
            'payload' => $payload,
            'processed_at' => now(),
        ]);

        if ($batchReference === '') {
            Log::warning('PayPal webhook missing payout batch reference', ['event_type' => $eventType]);
            return response()->json(['status' => 'accepted']);
        }

        if (str_starts_with($eventType, 'PAYMENT.PAYOUTS-ITEM.')) {
            $itemStatus = str_replace('PAYMENT.PAYOUTS-ITEM.', '', $eventType);
            $reconciliationService->reconcileSingleItemEvent(
                $batchReference,
                $itemStatus,
                (string) ($resource['payout_item_id'] ?? ''),
            );
        } elseif (str_starts_with($eventType, 'PAYMENT.PAYOUTSBATCH.')) {
            $reconciliationService->reconcileBatchByReference($batchReference);
        }

        return response()->json([
            'status' => 'ok',
            'event_id' => $event->id,
        ]);
    }
}
