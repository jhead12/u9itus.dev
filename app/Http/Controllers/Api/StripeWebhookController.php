<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\CampaignBillingService;
use App\Services\StripePaymentService;

class StripeWebhookController extends Controller
{
    public function handle(Request $request, StripePaymentService $stripe, CampaignBillingService $billing)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');

        try {
            $event = $stripe->parseWebhook($payload, $sigHeader);
        } catch (\Exception $e) {
            Log::warning('Stripe webhook verification failed: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid webhook'], 400);
        }

        // Determine event type and handle important events
        $type = null;
        if (is_string($event)) {
            $data = json_decode($event, true);
            $type = $data['type'] ?? null;
            $payloadEvent = $data ?? null;
        } elseif (is_array($event)) {
            $type = $event['type'] ?? null;
            $payloadEvent = $event;
        } else {
            $type = $event->type ?? null;
            $payloadEvent = $event;
        }

        Log::info('Stripe webhook received', ['type' => $type]);

        // Handle payment_intent.succeeded
        if ($type === 'payment_intent.succeeded') {
            // extract payment_intent id
            $piId = null;
            if (is_object($payloadEvent) && isset($payloadEvent->data->object->id)) {
                $piId = $payloadEvent->data->object->id;
            } elseif (is_array($payloadEvent) && isset($payloadEvent['data']['object']['id'])) {
                $piId = $payloadEvent['data']['object']['id'];
            }

            if ($piId) {
                $billing->finalizePaymentIntent($piId, $event);
            } else {
                Log::warning('payment_intent.succeeded missing id in payload');
            }
        }

        // Handle payment_intent.payment_failed
        if ($type === 'payment_intent.payment_failed') {
            $piId = null;
            if (is_object($payloadEvent) && isset($payloadEvent->data->object->id)) {
                $piId = $payloadEvent->data->object->id;
            } elseif (is_array($payloadEvent) && isset($payloadEvent['data']['object']['id'])) {
                $piId = $payloadEvent['data']['object']['id'];
            }

            if ($piId) {
                $billing->finalizePaymentIntent($piId, $event);
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
