<?php

namespace App\Services;

use App\Models\ViewSession;
use App\Models\Voter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
    public function notifyViewSessionCompleted(ViewSession $session): void
    {
        if (! $this->enabled()) {
            return;
        }

        $voter = $session->voter;
        if (! $voter || ! $voter->earlybank_member_id) {
            return; // Not an Early-bank referred voter.
        }

        // Determine if this is the voter's first completed session — Early-bank
        // pays the $10 flat referral bonus once, on first qualifying activity.
        $isFirstCompletion = ViewSession::query()
            ->where('voter_id', $voter->id)
            ->where('id', '<', $session->id)
            ->whereNotNull('completed_at')
            ->doesntExist();

        if ($isFirstCompletion) {
            $this->dispatch('voter.referred', [
                'voter_uuid'          => $voter->uuid,
                'earlybank_member_id' => $voter->earlybank_member_id,
                'linked_at'           => optional($voter->earlybank_linked_at)->toIso8601String(),
                'first_session_uuid'  => $session->uuid,
            ]);
        }

        $this->dispatch('voter.earned', [
            'voter_uuid'          => $voter->uuid,
            'earlybank_member_id' => $voter->earlybank_member_id,
            'session_uuid'        => $session->uuid,
            'payout_amount'       => (float) $session->payout_amount,
            'completed_at'        => optional($session->completed_at)->toIso8601String() ?? now()->toIso8601String(),
        ]);
    }

    public function dispatch(string $eventType, array $data): void
    {
        $url    = (string) config('services.earlybank.webhook_url', '');
        $secret = (string) config('services.earlybank.webhook_secret', '');

        if ($url === '' || $secret === '') {
            Log::warning('EarlyBankWebhookService: webhook URL or secret missing; skipping dispatch', [
                'event' => $eventType,
            ]);
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

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'Content-Type'             => 'application/json',
                    'X-EarlyBank-Timestamp'    => $timestamp,
                    'X-EarlyBank-Signature'    => 't=' . $timestamp . ',v1=' . $signature,
                ])
                ->withBody($body, 'application/json')
                ->post($url);

            if (! $response->successful()) {
                Log::warning('EarlyBankWebhookService: non-2xx response', [
                    'event'  => $eventType,
                    'status' => $response->status(),
                    'body'   => mb_substr((string) $response->body(), 0, 500),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('EarlyBankWebhookService: dispatch failed', [
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
