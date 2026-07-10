<?php

namespace App\Services;

use App\Mail\EarlyBankReferralEnrolledMail;
use App\Models\EarlyBankWebhookLog;
use App\Models\ViewSession;
use App\Models\Voter;
use App\Services\PlatformSettingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * EarlyBankWebhookService
 *
 * Dispatches signed outbound webhooks to Early-bank.com whenever a voter
 * who was referred by an Early-bank member earns money on u9itus.
 *
 * Early-bank uses these events to credit:
 *   - $10 flat referral bonus on the FIRST view session (event: voter.referred)
 *   - 10% commission of the voter's payout on EVERY view (event: voter.earned)
 *
 * Delivery model:
 *   - Fire-and-forget: failures are logged but do not block u9itus payouts.
 *   - HMAC-SHA256 signature in X-EarlyBank-Signature header (timestamp prefix).
 *   - Should be called from outside the DB transaction that credits the voter.
 */
class EarlyBankWebhookService
{
    /**
     * Handle the full Early-bank side-effect when a voter is linked to an EB
     * member at registration time: fires `voter.registered` webhook and queues
     * the enrollment confirmation email.
     *
     * Extracted here so both the API path (VoterController) and the web-form
     * path (AuthController) share the same behaviour without duplication.
     *
     * Fire-and-forget: failures are logged but must not block registration.
     */
    public function handleVoterRegistered(Voter $voter, string $memberId): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->dispatch('voter.registered', [
            'voter_uuid'          => $voter->uuid,
            'earlybank_member_id' => $memberId,
            'registered_at'       => optional($voter->created_at)->toIso8601String() ?? now()->toIso8601String(),
        ]);

        if (! empty($voter->email)) {
            try {
                Mail::to($voter->email)->queue(new EarlyBankReferralEnrolledMail($voter, $memberId));
            } catch (Throwable $e) {
                Log::warning('EarlyBankWebhookService: enrollment email failed', [
                    'voter_uuid' => $voter->uuid,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }

    public function notifyViewSessionCompleted(ViewSession $session): void
    {
        if (! $this->enabled()) {
            return;
        }

        $voter = $session->voter;
        if (! $voter || ! $voter->earlybank_member_id) {
            return; // Not an Early-bank referred voter.
        }

        // Gate: voter must have completed Stripe Connect before any EB commission
        // events fire. Voters with only legacy KYC are excluded — they cannot
        // receive or withdraw payouts.
        if ($voter->stripe_account_status !== 'active') {
            Log::info('EarlyBankWebhookService: skipping commission events — voter lacks active Stripe account', [
                'voter_uuid'          => $voter->uuid,
                'earlybank_member_id' => $voter->earlybank_member_id,
                'stripe_status'       => $voter->stripe_account_status,
                'session_uuid'        => $session->uuid,
            ]);
            return;
        }

        // $10 referral bonus — only fire here when the trigger mode is 'first_verified_view'.
        // In 'stripe_verification' mode the bonus is dispatched by StripeWebhookController
        // the moment the voter's Stripe account transitions to active.
        $trigger = PlatformSettingsService::get('earlybank_referral_bonus_trigger', null, 'stripe_verification');

        if ($trigger === 'first_verified_view') {
            // Check for any prior completed sessions for this voter.
            // We already confirmed stripe_account_status is active above,
            // so any completed session counts — no need to re-join on voter.
            $isFirstVerifiedCompletion = ViewSession::query()
                ->where('voter_id', $voter->id)
                ->where('id', '<', $session->id)
                ->whereNotNull('completed_at')
                ->doesntExist();

            if ($isFirstVerifiedCompletion) {
                $this->dispatch('voter.referred', [
                    'voter_uuid'          => $voter->uuid,
                    'earlybank_member_id' => $voter->earlybank_member_id,
                    'linked_at'           => optional($voter->earlybank_linked_at)->toIso8601String(),
                    'first_session_uuid'  => $session->uuid,
                ]);
            }
        }

        // 10% per-view commission — always fires for active Stripe voters.
        $this->dispatch('voter.earned', [
            'voter_uuid'          => $voter->uuid,
            'earlybank_member_id' => $voter->earlybank_member_id,
            'session_uuid'        => $session->uuid,
            'payout_amount'       => (float) $session->payout_amount,
            'completed_at'        => optional($session->completed_at)->toIso8601String() ?? now()->toIso8601String(),
        ]);
    }

    /**
     * Fire the $10 referral bonus at Stripe verification time.
     * Called by StripeWebhookController when `earlybank_referral_bonus_trigger = stripe_verification`.
     * Guards against double-firing: skips if a voter.referred log already exists for this voter.
     */
    public function dispatchReferralBonusOnVerification(Voter $voter): void
    {
        if (! $this->enabled()) {
            return;
        }

        if (! $voter->earlybank_member_id) {
            return;
        }

        // Idempotency: only fire once per voter regardless of repeated Stripe events.
        $alreadyFired = EarlyBankWebhookLog::where('voter_uuid', $voter->uuid)
            ->where('event_type', 'voter.referred')
            ->where('delivered', true)
            ->exists();

        if ($alreadyFired) {
            Log::info('EarlyBankWebhookService: voter.referred already delivered, skipping duplicate', [
                'voter_uuid' => $voter->uuid,
            ]);
            return;
        }

        $this->dispatch('voter.referred', [
            'voter_uuid'          => $voter->uuid,
            'earlybank_member_id' => $voter->earlybank_member_id,
            'linked_at'           => optional($voter->earlybank_linked_at)->toIso8601String(),
            'verification_trigger' => true,
        ]);
    }

    public function dispatch(string $eventType, array $data): void
    {
        $url    = (string) config('services.earlybank.webhook_url', '');
        $secret = (string) config('services.earlybank.webhook_secret', '');

        $logBase = [
            'event_type'          => $eventType,
            'voter_uuid'          => $data['voter_uuid'] ?? null,
            'earlybank_member_id' => $data['earlybank_member_id'] ?? null,
            'view_session_uuid'   => $data['session_uuid'] ?? $data['first_session_uuid'] ?? null,
            'payout_amount'       => isset($data['payout_amount']) ? (float) $data['payout_amount'] : null,
        ];

        if ($url === '' || $secret === '') {
            Log::warning('EarlyBankWebhookService: webhook URL or secret missing; skipping dispatch', [
                'event' => $eventType,
            ]);

            EarlyBankWebhookLog::create(array_merge($logBase, [
                'payload'       => $data,
                'delivered'     => false,
                'error_message' => 'Webhook URL or secret not configured.',
            ]));

            return;
        }

        $payload = [
            'event'       => $eventType,
            'occurred_at' => now()->toIso8601String(),
            'data'        => $data,
        ];

        $body      = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);

        $httpStatus    = null;
        $delivered     = false;
        $errorMessage  = null;
        $deliveredAt   = null;

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'Content-Type'             => 'application/json',
                    'X-EarlyBank-Timestamp'    => $timestamp,
                    'X-EarlyBank-Signature'    => 't=' . $timestamp . ',v1=' . $signature,
                ])
                ->withBody($body, 'application/json')
                ->post($url);

            $httpStatus = $response->status();
            $delivered  = $response->successful();

            if ($delivered) {
                $deliveredAt = now();
            } else {
                $errorMessage = mb_substr((string) $response->body(), 0, 500);
                Log::warning('EarlyBankWebhookService: non-2xx response', [
                    'event'  => $eventType,
                    'status' => $httpStatus,
                    'body'   => $errorMessage,
                ]);
            }
        } catch (Throwable $e) {
            $errorMessage = mb_substr($e->getMessage(), 0, 500);
            Log::warning('EarlyBankWebhookService: dispatch failed', [
                'event'   => $eventType,
                'message' => $errorMessage,
            ]);
        }

        try {
            EarlyBankWebhookLog::create(array_merge($logBase, [
                'payload'      => $data,
                'http_status'  => $httpStatus,
                'delivered'    => $delivered,
                'error_message' => $errorMessage,
                'delivered_at' => $deliveredAt,
            ]));
        } catch (Throwable $e) {
            // Never let logging failure break the webhook flow.
            Log::error('EarlyBankWebhookService: failed to persist webhook log', [
                'event'   => $eventType,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function enabled(): bool
    {
        return (bool) config('services.earlybank.enabled', false);
    }
}
