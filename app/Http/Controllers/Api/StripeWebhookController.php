<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voter;
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

        [$type, $payloadEvent] = $this->normalizeEvent($event);

        Log::info('Stripe webhook received', ['type' => $type]);

        if ($type === 'payment_intent.succeeded' || $type === 'payment_intent.payment_failed') {
            $this->handlePaymentIntentEvent($payloadEvent, $event, $billing, $type);
        }

        if ($type === 'account.updated') {
            $this->handleAccountUpdatedEvent($payloadEvent);
        }

        return response()->json(['status' => 'ok']);
    }

    private function normalizeEvent(mixed $event): array
    {
        if (is_string($event)) {
            $data = json_decode($event, true);
            return [$data['type'] ?? null, $data ?? null];
        }

        if (is_array($event)) {
            return [$event['type'] ?? null, $event];
        }

        return [$event->type ?? null, $event];
    }

    private function paymentIntentIdFromPayload(mixed $payloadEvent): ?string
    {
        if (is_object($payloadEvent) && isset($payloadEvent->data->object->id)) {
            return (string) $payloadEvent->data->object->id;
        }

        if (is_array($payloadEvent) && isset($payloadEvent['data']['object']['id'])) {
            return (string) $payloadEvent['data']['object']['id'];
        }

        return null;
    }

    private function handlePaymentIntentEvent(mixed $payloadEvent, mixed $event, CampaignBillingService $billing, string $type): void
    {
        $piId = $this->paymentIntentIdFromPayload($payloadEvent);
        if (! $piId) {
            Log::warning($type . ' missing id in payload');
            return;
        }

        $billing->finalizePaymentIntent($piId, $event);
    }

    private function handleAccountUpdatedEvent(mixed $payloadEvent): void
    {
        [$accountId, $chargesEnabled, $payoutsEnabled] = $this->extractAccountUpdatedData($payloadEvent);

        if (! $accountId) {
            return;
        }

        $status = ($chargesEnabled && $payoutsEnabled) ? 'active' : 'pending';

        $voters = Voter::where('stripe_account_id', $accountId)->get();
        foreach ($voters as $voter) {
            $voter->update([
                'stripe_account_status' => $status,
                // Keep legacy compatibility for existing verification checks.
                'is_verified' => $status === 'active' ? true : $voter->is_verified,
            ]);

            if ($status === 'active' && $voter->user) {
                $voter->user->update(['is_verified' => true]);
            }
        }
    }

    private function extractAccountUpdatedData(mixed $payloadEvent): array
    {
        if (is_object($payloadEvent) && isset($payloadEvent->data->object->id)) {
            return [
                (string) $payloadEvent->data->object->id,
                (bool) ($payloadEvent->data->object->charges_enabled ?? false),
                (bool) ($payloadEvent->data->object->payouts_enabled ?? false),
            ];
        }

        if (is_array($payloadEvent) && isset($payloadEvent['data']['object']['id'])) {
            return [
                (string) $payloadEvent['data']['object']['id'],
                (bool) ($payloadEvent['data']['object']['charges_enabled'] ?? false),
                (bool) ($payloadEvent['data']['object']['payouts_enabled'] ?? false),
            ];
        }

        return [null, false, false];
    }
}
