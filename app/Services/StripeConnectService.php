<?php

namespace App\Services;

use App\Exceptions\StripeConnectException;
use App\Models\Voter;
use Illuminate\Support\Facades\Log;

class StripeConnectService
{
    private const NOT_CONFIGURED = 'Stripe Connect is not configured.';

    protected ?\Stripe\StripeClient $client = null;

    public function __construct()
    {
        if (! class_exists('\Stripe\StripeClient')) {
            Log::warning('Stripe SDK not installed. Stripe Connect is disabled.');
            return;
        }

        $secret = (string) config('services.stripe.secret', '');
        if ($secret === '') {
            Log::warning('Stripe secret key not configured. Stripe Connect is disabled.');
            return;
        }

        $this->client = new \Stripe\StripeClient($secret);
    }

    public function isConfigured(): bool
    {
        return $this->client !== null;
    }

    public function ensureExpressAccount(Voter $voter): string
    {
        if (! $this->client) {
            throw new StripeConnectException(self::NOT_CONFIGURED);
        }

        if (! empty($voter->stripe_account_id)) {
            return (string) $voter->stripe_account_id;
        }

        $account = $this->client->accounts->create([
            'type' => 'express',
            'country' => 'US',
            'email' => $voter->email,
            'capabilities' => [
                'transfers' => ['requested' => true],
            ],
            'metadata' => [
                'voter_id' => (string) $voter->id,
                'voter_uuid' => (string) $voter->uuid,
            ],
        ]);

        $voter->update([
            'stripe_account_id' => $account->id,
            'stripe_account_status' => 'pending',
        ]);

        return (string) $account->id;
    }

    public function createOnboardingLink(Voter $voter, ?string $returnUrl = null, ?string $refreshUrl = null): array
    {
        if (! $this->client) {
            throw new StripeConnectException(self::NOT_CONFIGURED);
        }

        $accountId = $this->ensureExpressAccount($voter);
        $defaultReturn = (string) config('services.stripe.connect_return_url', rtrim((string) config('app.url'), '/') . '/payout');
        $defaultRefresh = (string) config('services.stripe.connect_refresh_url', rtrim((string) config('app.url'), '/') . '/payout');

        $link = $this->client->accountLinks->create([
            'account' => $accountId,
            'type' => 'account_onboarding',
            'return_url' => $returnUrl ?: $defaultReturn,
            'refresh_url' => $refreshUrl ?: $defaultRefresh,
        ]);

        return [
            'url' => (string) $link->url,
            'expires_at' => (int) $link->expires_at,
            'account_id' => $accountId,
        ];
    }

    public function canReceivePayout(Voter $voter): bool
    {
        return ! empty($voter->stripe_account_id)
            && $voter->stripe_account_status === 'active';
    }

    public function getAccountStatus(Voter $voter): array
    {
        if (! $this->client) {
            throw new StripeConnectException(self::NOT_CONFIGURED);
        }

        if (empty($voter->stripe_account_id)) {
            return [
                'status' => 'missing',
                'charges_enabled' => false,
                'payouts_enabled' => false,
                'requirements_due' => [],
            ];
        }

        $account = $this->client->accounts->retrieve((string) $voter->stripe_account_id, []);

        $isActive = (bool) $account->charges_enabled && (bool) $account->payouts_enabled;
        $status = $isActive ? 'active' : 'pending';

        $voter->update([
            'stripe_account_status' => $status,
            'is_verified' => $isActive ? true : $voter->is_verified,
        ]);

        if ($isActive && $voter->user) {
            $voter->user->update(['is_verified' => true]);
        }

        return [
            'status' => $status,
            'charges_enabled' => (bool) $account->charges_enabled,
            'payouts_enabled' => (bool) $account->payouts_enabled,
            'requirements_due' => (array) ($account->requirements->currently_due ?? []),
            'account_id' => (string) $account->id,
        ];
    }

    public function sendTransfer(Voter $voter, float $amount, string $idempotencyKey, array $metadata = []): array
    {
        if (! $this->client) {
            throw new StripeConnectException(self::NOT_CONFIGURED);
        }

        if (! $this->canReceivePayout($voter)) {
            throw new StripeConnectException('Voter Stripe account is not active for payouts.');
        }

        $amountCents = (int) round($amount * 100);
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('Payout amount must be greater than zero.');
        }

        $transfer = $this->client->transfers->create([
            'amount' => $amountCents,
            'currency' => 'usd',
            'destination' => (string) $voter->stripe_account_id,
            'description' => 'U9itus viewer earnings payout',
            'metadata' => array_merge([
                'voter_id' => (string) $voter->id,
                'voter_uuid' => (string) $voter->uuid,
            ], $metadata),
        ], [
            'idempotency_key' => $idempotencyKey,
        ]);

        return [
            'reference' => (string) $transfer->id,
            'fee' => 0.00,
            'transfer' => $transfer,
        ];
    }
}
