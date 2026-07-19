<?php

namespace App\Services;

use App\Models\Citizen;
use App\Models\EarlyBankEarning;
use App\Models\Politician;
use App\Models\Voter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * EarlyBankInboundService
 *
 * Processes signed inbound webhooks from Early-bank.com and updates U9itus
 * state accordingly. EB remains the source of truth for commission amounts;
 * this service only records what EB reports and syncs membership status.
 *
 * Supported event types:
 *   - payout.commission      : EB paid a view commission to a referring member
 *   - payout.bonus           : EB paid a referral bonus to a referring member
 *   - member.status          : EB membership tier changed for a U9itus user
 *   - politician.purchased   : EB acknowledges a politician-procurement event
 *                              (used to reconcile the outbound webhook we fire)
 */
class EarlyBankInboundService
{
    public function __construct()
    {
        // No dependencies.
    }

    /**
     * Verify the inbound HMAC signature and, if valid, process the payload.
     *
     * Returns an array suitable for the controller JSON response.
     *
     * @return array{verified: bool, processed: bool, event_id: string|null, error: string|null}
     */
    public function handleWebhook(Request $request): array
    {
        $secret = (string) config('services.earlybank.webhook_secret', '');

        if ($secret === '') {
            return [
                'verified'   => false,
                'processed'  => false,
                'event_id'   => null,
                'error'      => 'inbound_webhook_secret_not_configured',
            ];
        }

        $timestampHeader = (string) $request->header('X-EarlyBank-Timestamp', '');
        $signatureHeader = (string) $request->header('X-EarlyBank-Signature', '');
        $body            = (string) $request->getContent();

        if (! $this->verifySignature($timestampHeader, $signatureHeader, $body, $secret)) {
            Log::warning('EarlyBankInboundService: inbound webhook signature mismatch', [
                'ip'        => $request->ip(),
                'path'      => $request->path(),
                'timestamp' => $timestampHeader,
            ]);

            return [
                'verified'   => false,
                'processed'  => false,
                'event_id'   => null,
                'error'      => 'invalid_signature',
            ];
        }

        $payload = $request->all();
        $eventId = $this->string($payload, 'event_id') ?? $this->string($payload, 'id') ?? null;

        try {
            $result = $this->processPayload($payload, $body);

            return [
                'verified'   => true,
                'processed'  => $result['processed'] ?? true,
                'event_id'   => $eventId,
                'error'      => $result['error'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('EarlyBankInboundService: failed to process webhook', [
                'event_id' => $eventId,
                'event'    => $payload['event'] ?? null,
                'error'    => $e->getMessage(),
            ]);

            return [
                'verified'   => true,
                'processed'  => false,
                'event_id'   => $eventId,
                'error'      => 'processing_failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verify the X-EarlyBank-Signature header.
     *
     * Expected format: t={unixtimestamp},v1={hmac}
     * Signature base:  {timestamp}.{json_body}
     */
    public function verifySignature(
        string $timestampHeader,
        string $signatureHeader,
        string $body,
        string $secret
    ): bool {
        if ($timestampHeader === '' || $signatureHeader === '') {
            return false;
        }

        if (! preg_match('/^t=(\d+),v1=([a-f0-9]{64})$/i', $signatureHeader, $m)) {
            return false;
        }

        $timestamp = $m[1];
        $provided  = strtolower($m[2]);
        $expected  = hash_hmac('sha256', $timestamp . '.' . $body, $secret);

        if (! hash_equals($expected, $provided)) {
            return false;
        }

        // Reject events older than 5 minutes to mitigate replay attacks.
        $ts = (int) $timestamp;
        if ($ts === 0 || abs(time() - $ts) > 300) {
            return false;
        }

        return true;
    }

    /**
     * Route a verified payload to the correct processor.
     *
     * @param array<string, mixed> $payload
     * @return array{processed: bool, error: string|null}
     */
    public function processPayload(array $payload, string $rawBody): array
    {
        $eventType = $this->string($payload, 'event');

        if ($eventType === null || $eventType === '') {
            return ['processed' => false, 'error' => 'missing_event_type'];
        }

        $data = $payload['data'] ?? [];
        if (! is_array($data)) {
            return ['processed' => false, 'error' => 'missing_data_payload'];
        }

        return match ($eventType) {
            EarlyBankEarning::EVENT_PAYOUT_COMMISSION => $this->processPayoutCommission($data, $payload, $rawBody),
            EarlyBankEarning::EVENT_PAYOUT_BONUS      => $this->processPayoutBonus($data, $payload, $rawBody),
            EarlyBankEarning::EVENT_MEMBER_STATUS     => $this->processMemberStatus($data, $payload, $rawBody),
            EarlyBankEarning::EVENT_POLITICIAN_PURCHASED => $this->processPoliticianPurchased($data, $payload, $rawBody),
            default => ['processed' => false, 'error' => 'unsupported_event_type: ' . $eventType],
        };
    }

    /**
     * Process payout.commission: a referring member earned a view commission.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $payload
     */
    private function processPayoutCommission(array $data, array $payload, string $rawBody): array
    {
        $memberId = $this->requireUuid($data, 'earlybank_member_id');
        $amount   = $this->requirePositiveAmount($data, 'payout_amount');

        // Resolve the U9itus entity that owns this EB member account.
        $owner = $this->resolveMemberOwner($memberId);
        if ($owner === null) {
            return ['processed' => false, 'error' => 'member_not_found: ' . $memberId];
        }

        $earning = $this->upsertEarning([
            'event_type'              => EarlyBankEarning::EVENT_PAYOUT_COMMISSION,
            'earlybank_event_id'      => $this->string($payload, 'event_id'),
            'earlybank_member_id'     => $memberId,
            'voter_id'                => $owner instanceof Voter ? $owner->id : null,
            'politician_id'           => $owner instanceof Politician ? $owner->id : null,
            'citizen_id'              => $owner instanceof Citizen ? $owner->id : null,
            'referenced_voter_uuid'   => $this->string($data, 'voter_uuid'),
            'commission_amount'       => $amount,
            'payout_amount'           => $amount,
            'external_reference'      => $this->string($data, 'external_reference'),
            'idempotency_key'         => $this->idempotencyKey($data, $payload, $amount),
            'payload'                 => $payload,
        ]);

        return ['processed' => true, 'earning_id' => $earning->id, 'error' => null];
    }

    /**
     * Process payout.bonus: a referring member earned a flat referral bonus.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $payload
     */
    private function processPayoutBonus(array $data, array $payload, string $rawBody): array
    {
        $memberId = $this->requireUuid($data, 'earlybank_member_id');
        $amount   = $this->requirePositiveAmount($data, 'bonus_amount');

        $owner = $this->resolveMemberOwner($memberId);
        if ($owner === null) {
            return ['processed' => false, 'error' => 'member_not_found: ' . $memberId];
        }

        $earning = $this->upsertEarning([
            'event_type'              => EarlyBankEarning::EVENT_PAYOUT_BONUS,
            'earlybank_event_id'      => $this->string($payload, 'event_id'),
            'earlybank_member_id'     => $memberId,
            'voter_id'                => $owner instanceof Voter ? $owner->id : null,
            'politician_id'           => $owner instanceof Politician ? $owner->id : null,
            'citizen_id'              => $owner instanceof Citizen ? $owner->id : null,
            'referenced_voter_uuid'   => $this->string($data, 'voter_uuid'),
            'bonus_amount'            => $amount,
            'payout_amount'           => $amount,
            'external_reference'      => $this->string($data, 'external_reference'),
            'idempotency_key'         => $this->idempotencyKey($data, $payload, $amount),
            'payload'                 => $payload,
        ]);

        return ['processed' => true, 'earning_id' => $earning->id, 'error' => null];
    }

    /**
     * Process member.status: sync Early-bank subscription status on the matching
     * voter, politician, or citizen.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $payload
     */
    private function processMemberStatus(array $data, array $payload, string $rawBody): array
    {
        $memberId = $this->requireUuid($data, 'earlybank_member_id');
        $status   = $this->string($data, 'subscription_status');

        if ($status === null || ! in_array($status, [
            EarlyBankEarning::SUBSCRIPTION_INACTIVE,
            EarlyBankEarning::SUBSCRIPTION_FREE,
            EarlyBankEarning::SUBSCRIPTION_PAID,
            EarlyBankEarning::SUBSCRIPTION_SUSPENDED,
        ], true)) {
            return ['processed' => false, 'error' => 'invalid_subscription_status: ' . ($status ?? 'null')];
        }

        $owner = $this->resolveMemberOwner($memberId);
        if ($owner === null) {
            return ['processed' => false, 'error' => 'member_not_found: ' . $memberId];
        }

        $owner->forceFill(['earlybank_subscription_status' => $status])->save();

        // Also record an audit row so the status history is visible.
        $this->upsertEarning([
            'event_type'          => EarlyBankEarning::EVENT_MEMBER_STATUS,
            'earlybank_event_id'  => $this->string($payload, 'event_id'),
            'earlybank_member_id' => $memberId,
            'voter_id'            => $owner instanceof Voter ? $owner->id : null,
            'politician_id'       => $owner instanceof Politician ? $owner->id : null,
            'citizen_id'          => $owner instanceof Citizen ? $owner->id : null,
            'external_reference'  => $status,
            'idempotency_key'     => $this->idempotencyKey($data, $payload, 0),
            'payload'             => $payload,
            'status'              => EarlyBankEarning::STATUS_SETTLED,
            'settled_at'          => now(),
        ]);

        return ['processed' => true, 'error' => null];
    }

    /**
     * Process politician.purchased: EB acknowledges a procurement commission.
     *
     * We do NOT credit anything here; the actual payout comes through a later
     * payout.commission event. This event just records the acknowledgement so
     * admins can reconcile outbound vs inbound traffic.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $payload
     */
    private function processPoliticianPurchased(array $data, array $payload, string $rawBody): array
    {
        $memberId = $this->requireUuid($data, 'earlybank_member_id');
        $amount   = $this->requirePositiveAmount($data, 'purchase_amount');

        $owner = $this->resolveMemberOwner($memberId);
        if ($owner === null) {
            return ['processed' => false, 'error' => 'member_not_found: ' . $memberId];
        }

        $earning = $this->upsertEarning([
            'event_type'               => EarlyBankEarning::EVENT_POLITICIAN_PURCHASED,
            'earlybank_event_id'       => $this->string($payload, 'event_id'),
            'earlybank_member_id'      => $memberId,
            'voter_id'                 => $owner instanceof Voter ? $owner->id : null,
            'politician_id'            => $owner instanceof Politician ? $owner->id : null,
            'citizen_id'               => $owner instanceof Citizen ? $owner->id : null,
            'referenced_politician_uuid' => $this->string($data, 'politician_uuid'),
            'commission_amount'        => $amount, // EB purchase amount; actual commission is computed later
            'external_reference'       => $this->string($data, 'external_reference'),
            'idempotency_key'          => $this->idempotencyKey($data, $payload, $amount),
            'payload'                  => $payload,
        ]);

        return ['processed' => true, 'earning_id' => $earning->id, 'error' => null];
    }

    /**
     * Find the U9itus voter, politician, or citizen that owns the given EB
     * member UUID. Returns null if no match.
     */
    private function resolveMemberOwner(string $memberUuid): Voter|Politician|Citizen|null
    {
        $voter = Voter::where('earlybank_own_member_uuid', $memberUuid)->first();
        if ($voter) {
            return $voter;
        }

        $politician = Politician::where('earlybank_own_member_uuid', $memberUuid)->first();
        if ($politician) {
            return $politician;
        }

        $citizen = Citizen::where('earlybank_own_member_uuid', $memberUuid)->first();
        if ($citizen) {
            return $citizen;
        }

        return null;
    }

    /**
     * Upsert an EarlyBankEarning row idempotently by idempotency_key.
     *
     * @param array<string, mixed> $attributes
     */
    private function upsertEarning(array $attributes): EarlyBankEarning
    {
        $key = $attributes['idempotency_key'];

        $existing = EarlyBankEarning::where('idempotency_key', $key)->first();
        if ($existing) {
            // Update payload snapshot and status if EB re-sent a settled event.
            if (isset($attributes['status'])) {
                $existing->status = $attributes['status'];
            }
            if (isset($attributes['settled_at'])) {
                $existing->settled_at = $attributes['settled_at'];
            }
            $existing->payload = $attributes['payload'];
            $existing->save();

            return $existing;
        }

        return EarlyBankEarning::create($attributes);
    }

    /**
     * Build an idempotency key. Prefer EB's own key, otherwise derive one.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $payload
     */
    private function idempotencyKey(array $data, array $payload, float $amount): string
    {
        $ebKey = $this->string($payload, 'idempotency_key') ?? $this->string($data, 'idempotency_key');
        if ($ebKey !== null && $ebKey !== '') {
            return $ebKey;
        }

        return EarlyBankEarning::makeIdempotencyKey(
            (string) $payload['event'],
            $this->string($payload, 'event_id'),
            $this->string($data, 'earlybank_member_id'),
            $amount,
            $this->string($data, 'external_reference'),
        );
    }

    // ── Small payload helpers ─────────────────────────────────────────────────

    /**
     * Safely extract a string value from an array.
     *
     * @param array<string, mixed> $array
     */
    private function string(array $array, string $key): ?string
    {
        $value = $array[$key] ?? null;
        if (is_string($value) && $value !== '') {
            return $value;
        }
        return null;
    }

    /**
     * Require a UUID field from the payload.
     *
     * @param array<string, mixed> $data
     * @throws InvalidArgumentException
     */
    private function requireUuid(array $data, string $key): string
    {
        $value = $this->string($data, $key);
        if ($value === null || ! Str::isUuid($value)) {
            throw new InvalidArgumentException("missing or invalid {$key}");
        }
        return $value;
    }

    /**
     * Require a positive numeric amount from the payload.
     *
     * @param array<string, mixed> $data
     */
    private function requirePositiveAmount(array $data, string $key): float
    {
        $value = $data[$key] ?? null;
        if (! is_numeric($value) || (float) $value < 0) {
            throw new InvalidArgumentException("missing or invalid {$key}");
        }
        return (float) $value;
    }
}
