<?php

namespace App\Services;

use App\Exceptions\StripeConnectException;
use App\Models\Voter;
use Illuminate\Support\Facades\Log;

class StripeConnectService
{
    private const NOT_CONFIGURED = 'Stripe Connect is not configured.';
    private const CONNECT_NOT_ENABLED = 'Stripe Connect is not enabled for the configured Stripe account. Enable Connect in Stripe Dashboard and retry.';

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

        $email = trim((string) ($voter->email ?: optional($voter->user)->email ?: ''));

        $accountPayload = [
            'type' => 'express',
            'country' => 'US',
            'capabilities' => [
                'transfers' => ['requested' => true],
            ],
            'metadata' => [
                'voter_id' => (string) $voter->id,
                'voter_uuid' => (string) $voter->uuid,
            ],
        ];

        if ($email !== '') {
            $accountPayload['email'] = $email;

            // Keep voter email backfilled for legacy records.
            if ($voter->email !== $email) {
                $voter->forceFill(['email' => $email])->save();
            }
        } else {
            Log::warning('Creating Stripe Express account without voter email', [
                'voter_id' => $voter->id,
            ]);
        }

        try {
            $account = $this->client->accounts->create($accountPayload);
        } catch (\Throwable $e) {
            throw $this->classifyStripeException($e);
        }

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

        try {
            $link = $this->client->accountLinks->create([
                'account' => $accountId,
                'type' => 'account_onboarding',
                'return_url' => $returnUrl ?: $defaultReturn,
                'refresh_url' => $refreshUrl ?: $defaultRefresh,
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($this->isConnectNotEnabledError($e)) {
                throw new StripeConnectException(self::CONNECT_NOT_ENABLED, (int) $e->getCode(), $e);
            }

            $message = strtolower($e->getMessage());

            if (! empty($voter->stripe_account_id) && (
                str_contains($message, 'no such account') ||
                str_contains($message, 'no_such_account')
            )) {
                Log::warning('Stripe account missing in Stripe, recreating for voter.', [
                    'voter_id' => $voter->id,
                    'stale_account_id' => $voter->stripe_account_id,
                ]);

                $voter->update([
                    'stripe_account_id' => null,
                    'stripe_account_status' => null,
                ]);

                $accountId = $this->ensureExpressAccount($voter);

                $link = $this->client->accountLinks->create([
                    'account' => $accountId,
                    'type' => 'account_onboarding',
                    'return_url' => $returnUrl ?: $defaultReturn,
                    'refresh_url' => $refreshUrl ?: $defaultRefresh,
                ]);
            } else {
                // Try account_update fallback; if that also fails, classify and rethrow.
                try {
                    $link = $this->fallbackToAccountUpdateLink(
                        $voter,
                        $accountId,
                        $returnUrl ?: $defaultReturn,
                        $refreshUrl ?: $defaultRefresh,
                        $e
                    );
                } catch (\Stripe\Exception\InvalidRequestException $fallbackE) {
                    throw $this->classifyStripeException($fallbackE);
                }
            }
        } catch (\Stripe\Exception\AuthenticationException $e) {
            throw $this->classifyStripeException($e);
        }

        return [
            'url' => (string) $link->url,
            'expires_at' => (int) $link->expires_at,
            'account_id' => $accountId,
        ];
    }

    /**
     * Some legacy/partially configured Stripe accounts cannot open onboarding again.
     * Retry with account_update so users can still complete required updates.
     */
    private function fallbackToAccountUpdateLink(
        Voter $voter,
        string $accountId,
        string $returnUrl,
        string $refreshUrl,
        \Stripe\Exception\InvalidRequestException $exception
    ): \Stripe\AccountLink {
        try {
            Log::info('Retrying Stripe account link as account_update for voter.', [
                'voter_id' => $voter->id,
                'stripe_account_id' => $accountId,
                'original_error' => $exception->getMessage(),
            ]);

            return $this->client->accountLinks->create([
                'account' => $accountId,
                'type' => 'account_update',
                'return_url' => $returnUrl,
                'refresh_url' => $refreshUrl,
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $fallbackException) {
            // Always classify — caller catches StripeConnectException.
            throw $this->classifyStripeException($fallbackException);
        }
    }

    private function isConnectNotEnabledError(\Stripe\Exception\InvalidRequestException $e): bool
    {
        return str_contains(
            strtolower($e->getMessage()),
            'you can only create new accounts if you\'ve signed up for connect'
        );
    }

    /**
     * Map any raw Stripe exception to a user-safe StripeConnectException.
     *
     * Keeps sensitive Stripe internals in structured logs only; the message
     * returned here is safe to surface directly to the end user.
     */
    public function classifyStripeException(\Throwable $e): StripeConnectException
    {
        // Already classified — pass through.
        if ($e instanceof StripeConnectException) {
            return $e;
        }

        // Wrong API key / live-test mode mismatch.
        if ($e instanceof \Stripe\Exception\AuthenticationException) {
            return new StripeConnectException(
                'Payout setup is temporarily unavailable due to a configuration issue. Please contact support.',
                (int) $e->getCode(), $e
            );
        }

        if (! $e instanceof \Stripe\Exception\InvalidRequestException) {
            return new StripeConnectException(
                'We could not reach the payout provider right now. Please try again shortly.',
                (int) $e->getCode(), $e
            );
        }

        // Connect not enabled on the platform account.
        if ($this->isConnectNotEnabledError($e)) {
            return new StripeConnectException(self::CONNECT_NOT_ENABLED, (int) $e->getCode(), $e);
        }

        $errorCode  = (string) ($e->getError()?->code ?? '');
        $errorParam = (string) ($e->getError()?->param ?? '');
        $msgLower   = strtolower($e->getMessage());

        // URL issues — most common on Railway when env vars are missing/wrong.
        if (
            str_contains($errorParam, 'return_url') ||
            str_contains($errorParam, 'refresh_url') ||
            $errorCode === 'url_invalid' ||
            str_contains($msgLower, 'url')
        ) {
            return new StripeConnectException(
                'Payout setup is temporarily misconfigured (invalid redirect URL). Please contact support.',
                (int) $e->getCode(), $e
            );
        }

        // Closed or deleted account.
        if (in_array($errorCode, ['account_closed', 'account_invalid'], true) ||
            str_contains($msgLower, 'account has been closed')
        ) {
            return new StripeConnectException(
                'The linked payout account has been closed. Please contact support to reset it.',
                (int) $e->getCode(), $e
            );
        }

        // Live/test key mismatch.
        if (
            str_contains($msgLower, 'test mode') ||
            str_contains($msgLower, 'live mode') ||
            str_contains($msgLower, 'api key operates in')
        ) {
            return new StripeConnectException(
                'Payout setup is temporarily unavailable due to a configuration issue. Please contact support.',
                (int) $e->getCode(), $e
            );
        }

        // Already-submitted onboarding link reuse.
        if (str_contains($msgLower, 'already been submitted')) {
            return new StripeConnectException(
                'Your verification has already been submitted. Please check your email for next steps, or try again.',
                (int) $e->getCode(), $e
            );
        }

        // Generic fallback with a safe description.
        return new StripeConnectException(
            'We could not start payout verification right now. Please try again shortly.',
            (int) $e->getCode(), $e
        );
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
