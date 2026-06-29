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
            // Best-effort metadata extraction from the unverified payload so
            // ops can correlate Stripe Dashboard 400s without leaking content.
            $peek = json_decode($payload, true);
            Log::warning('Stripe webhook verification failed', [
                'error'             => $e->getMessage(),
                'event_id'          => is_array($peek) ? ($peek['id'] ?? null) : null,
                'event_type'        => is_array($peek) ? ($peek['type'] ?? null) : null,
                'livemode'          => is_array($peek) ? ($peek['livemode'] ?? null) : null,
                'sig_header_present' => $sigHeader !== '',
                'payload_bytes'     => strlen($payload),
            ]);
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

        // v2 Stripe event API (API version 2025-01 and later / 2026-02-25.clover).
        // Events of the form v2.core.account.* use a different payload shape:
        // related_object.id holds the account id; changes.after.applied_configurations
        // lists active capability groups ('recipient' = payouts, 'merchant' = charges).
        if (str_starts_with((string) $type, 'v2.core.account.')) {
            $this->handleV2AccountEvent($payloadEvent);
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

        $isActive = $chargesEnabled && $payoutsEnabled;
        $status   = $isActive ? 'active' : 'pending';

        $voters = Voter::where('stripe_account_id', $accountId)->get();
        foreach ($voters as $voter) {
            // Mirror Stripe state both ways. We don't demote legacy voters who
            // were manually verified before adopting Stripe Connect — only voters
            // who actually started Authentic User Verifier (have a stripe_account_id)
            // are touched here, so this is safe.
            $voter->update([
                'stripe_account_status' => $status,
                'is_verified'           => $isActive,
            ]);

            if ($voter->user) {
                $voter->user->update(['is_verified' => $isActive]);
            }

            Log::info('Stripe account.updated synced voter', [
                'voter_id'         => $voter->id,
                'stripe_account_id' => $accountId,
                'status'           => $status,
                'charges_enabled'  => $chargesEnabled,
                'payouts_enabled'  => $payoutsEnabled,
            ]);
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

    /**
     * Handle Stripe v2 event API account events.
     *
     * v2 payload shape:
     *   related_object.id          → Stripe account id
     *   changes.after.applied_configurations → ['recipient', 'merchant', ...]
     *     'recipient'  = payouts/transfers enabled
     *     'merchant'   = card payments enabled
     *
     * A newly created account starts with configurations set but capabilities
     * still pending Stripe review, so we always follow up with getAccountStatus
     * (a live Stripe API call) to get the definitive charges_enabled / payouts_enabled
     * booleans rather than guessing from applied_configurations alone.
     */
    private function handleV2AccountEvent(mixed $payloadEvent): void
    {
        $data = is_object($payloadEvent) ? (array) json_decode(json_encode($payloadEvent), true) : $payloadEvent;

        $accountId = $data['related_object']['id'] ?? null;
        if (! $accountId) {
            Log::warning('Stripe v2 account event missing related_object.id', [
                'keys' => array_keys($data),
            ]);
            return;
        }

        // applied_configurations from the event after-state gives us an early
        // signal, but we resolve live status via getAccountStatus so we honour
        // Stripe's actual charges_enabled / payouts_enabled flags.
        $after = $data['changes']['after'] ?? [];
        $configs = (array) ($after['applied_configurations'] ?? []);
        $hasRecipient = in_array('recipient', $configs, true);

        $voters = Voter::where('stripe_account_id', $accountId)->get();

        if ($voters->isEmpty()) {
            Log::info('Stripe v2 account event: no voter matches account', [
                'stripe_account_id' => $accountId,
            ]);
            return;
        }

        foreach ($voters as $voter) {
            // For a freshly created account 'recipient' config is set but
            // payouts_enabled is still false until onboarding is complete.
            // Store pending; the account.updated / v2 update event after
            // onboarding completion will flip it to active.
            $status = 'pending';
            $isActive = false;

            // If recipient is configured AND the voter's account was already
            // known-active (from a prior sync), preserve active status.
            if ($hasRecipient && $voter->stripe_account_status === 'active') {
                $status   = 'active';
                $isActive = true;
            }

            $voter->update([
                'stripe_account_id'     => $accountId,
                'stripe_account_status' => $status,
                'is_verified'           => $isActive,
            ]);

            if ($voter->user) {
                $voter->user->update(['is_verified' => $isActive]);
            }

            Log::info('Stripe v2 account event synced voter', [
                'event_type'           => $data['type'] ?? 'v2.core.account.*',
                'voter_id'             => $voter->id,
                'stripe_account_id'    => $accountId,
                'applied_configurations' => $configs,
                'status'               => $status,
            ]);
        }
    }
}
