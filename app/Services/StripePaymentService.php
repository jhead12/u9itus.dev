<?php

namespace App\Services;

use App\Models\Politician;
use Illuminate\Support\Facades\Log;

class StripePaymentService
{
    protected $client;

    public function __construct()
    {
        // Lazy-load Stripe client if available
        if (class_exists('\Stripe\StripeClient')) {
            $key = config('services.stripe.secret');
            if (! empty($key)) {
                $this->client = new \Stripe\StripeClient($key);
            } else {
                $this->client = null;
                Log::warning('Stripe secret key not configured (STRIPE_SECRET env var missing). Payments are disabled.');
            }
        } else {
            $this->client = null;
            Log::warning('Stripe SDK not installed. Install stripe/stripe-php to enable payments.');
        }
    }

    /**
     * Create a PaymentIntent for a campaign charge.
     * Returns Stripe PaymentIntent id or throws when SDK missing.
     */
    public function createPaymentIntent(
        float $amount,
        string $currency = 'usd',
        array $metadata = [],
        ?string $customerId = null,
        ?string $paymentMethodId = null,
        ?string $idempotencyKey = null,
    ) {
        if (! $this->client) {
            throw new \RuntimeException('Stripe SDK not available. Run `composer require stripe/stripe-php`.');
        }

        $amountCents = (int) round($amount * 100);

        $params = [
            'amount'               => $amountCents,
            'currency'             => $currency,
            'payment_method_types' => ['card'],
            'metadata'             => $metadata,
        ];

        if ($customerId) {
            $params['customer'] = $customerId;
        }

        if ($paymentMethodId) {
            $params['payment_method'] = $paymentMethodId;
        }

        $requestOptions = $idempotencyKey ? ['idempotency_key' => $idempotencyKey] : [];

        $pi = $this->client->paymentIntents->create($params, $requestOptions);

        Log::info('Created Stripe PaymentIntent', ['id' => $pi->id, 'amount' => $amount]);

        return $pi;
    }

    /**
     * Retrieve or create a Stripe Customer for a billable model (Politician or Citizen).
     *
     * Saves `stripe_customer_id` back to the model so subsequent calls are instant.
     */
    public function ensureCustomer($billable): ?string
    {
        if (! $this->client) {
            Log::warning('Stripe SDK not available — cannot ensure customer.');
            return null;
        }

        $isCitizen = $billable instanceof \App\Models\Citizen;
        $isPolitician = $billable instanceof \App\Models\Politician;

        if (! $isCitizen && ! $isPolitician) {
            throw new \InvalidArgumentException('ensureCustomer expects Citizen or Politician model.');
        }

        $modelName = $isCitizen ? 'citizen' : 'politician';
        $displayName = $billable->full_name ?? $billable->business_name ?? optional($billable->user)->name ?? 'Customer';
        $email = optional($billable->user)->email ?? null;

        if (! empty($billable->stripe_customer_id)) {
            try {
                $this->client->customers->retrieve($billable->stripe_customer_id, []);
                return $billable->stripe_customer_id;
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                Log::warning('Stored Stripe customer missing, recreating customer.', [
                    $modelName . '_id'      => $billable->id,
                    'stripe_customer_id'   => $billable->stripe_customer_id,
                    'error'                => $e->getMessage(),
                ]);
            }
        }

        $metadata = [
            $modelName . '_id'   => $billable->id,
            $modelName . '_uuid' => $billable->uuid ?? null,
        ];

        $customer = $this->client->customers->create([
            'name'     => $displayName,
            'email'    => $email,
            'metadata' => $metadata,
        ]);

        $billable->stripe_customer_id = $customer->id;
        $billable->saveQuietly();

        Log::info('Created Stripe Customer for ' . $modelName, [
            $modelName . '_id'     => $billable->id,
            'stripe_customer_id'   => $customer->id,
        ]);

        return $customer->id;
    }

    /**
     * Verify webhook signature (returns payload array on success).
     *
     * STRIPE_WEBHOOK_SECRET may be a comma-separated list of `whsec_...` secrets
     * so the app can validate events from multiple Stripe endpoints (e.g. one for
     * `payment_intent.*`, another for `account.updated`) without re-deploys when
     * Stripe rotates a single secret.
     */
    public function parseWebhook(string $payload, string $sigHeader)
    {
        if (! $this->client) {
            // Fail closed — SDK must be present to verify webhooks
            if (! in_array(config('app.env'), ['local', 'testing'])) {
                throw new \RuntimeException(
                    'Stripe SDK is not installed. Cannot verify webhook signatures in non-local environments.'
                );
            }
            Log::warning('Stripe SDK not installed; webhook events will not be verified (dev/test only).');
            return json_decode($payload, true);
        }

        $secrets = $this->webhookSecrets();
        if (empty($secrets)) {
            // Fail closed — no secret means we cannot trust the payload
            if (! in_array(config('app.env'), ['local', 'testing'])) {
                throw new \RuntimeException(
                    'Stripe webhook secret is not configured (STRIPE_WEBHOOK_SECRET). ' .
                    'Cannot accept webhook payloads without signature verification.'
                );
            }
            Log::warning('Stripe webhook secret not configured (services.stripe.webhook_secret). Signature check skipped (dev/test only).');
            return json_decode($payload, true);
        }

        $lastError = null;
        foreach ($secrets as $index => $secret) {
            try {
                return \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
            } catch (\UnexpectedValueException $e) {
                // Malformed JSON — same for every secret, bail immediately.
                Log::error('Stripe webhook payload invalid: ' . $e->getMessage());
                throw $e;
            } catch (\Stripe\Exception\SignatureVerificationException $e) {
                $lastError = $e;
                Log::debug('Stripe webhook signature mismatch for configured secret.', [
                    'secret_index'  => $index,
                    'secret_prefix' => substr($secret, 0, 10),
                    'sig_header_prefix' => substr($sigHeader, 0, 24),
                ]);
                continue;
            }
        }

        Log::error('Stripe webhook signature verification failed against all configured secrets.', [
            'secrets_tried'    => count($secrets),
            'sig_header_present' => $sigHeader !== '',
            'sig_header_prefix' => substr($sigHeader, 0, 24),
            'payload_bytes'    => strlen($payload),
            'error'            => $lastError?->getMessage(),
        ]);

        throw $lastError ?? new \RuntimeException('Stripe webhook signature verification failed.');
    }

    /**
     * Parse one or more whsec_ secrets from STRIPE_WEBHOOK_SECRET.
     * Accepts comma, semicolon, or whitespace separators.
     */
    private function webhookSecrets(): array
    {
        $raw = (string) config('services.stripe.webhook_secret', '');
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_unique(array_filter($parts, fn ($s) => str_starts_with($s, 'whsec_'))));
    }

    /**
     * Detect configured Stripe mode from the secret key prefix.
     */
    public function configuredMode(): string
    {
        $key = (string) config('services.stripe.secret', '');

        if (str_starts_with($key, 'sk_live_')) {
            return 'live';
        }

        if (str_starts_with($key, 'sk_test_')) {
            return 'test';
        }

        return 'unknown';
    }

    /**
     * Resolve payment mode from a Stripe object that includes a livemode flag.
     */
    public function modeFromStripeObject($stripeObject, ?string $fallback = null): string
    {
        if (is_object($stripeObject) && isset($stripeObject->livemode)) {
            return $stripeObject->livemode ? 'live' : 'test';
        }

        if (is_array($stripeObject) && array_key_exists('livemode', $stripeObject)) {
            return $stripeObject['livemode'] ? 'live' : 'test';
        }

        return $fallback ?? $this->configuredMode();
    }

    /**
     * Create a Stripe refund by PaymentIntent id.
     */
    public function createRefundForPaymentIntent(string $paymentIntentId, float $amount, array $metadata = [])
    {
        if (! $this->client) {
            throw new \LogicException('Stripe SDK not available. Run `composer require stripe/stripe-php`.');
        }

        $amountCents = (int) round($amount * 100);
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('Refund amount must be greater than zero.');
        }

        return $this->client->refunds->create([
            'payment_intent' => $paymentIntentId,
            'amount' => $amountCents,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Create a SetupIntent to securely save a card for future off-session use.
     * Returns the Stripe SetupIntent object (caller needs client_secret for the frontend).
     */
    public function createSetupIntent(string $customerId): object
    {
        if (! $this->client) {
            throw new \RuntimeException('Stripe SDK not available. Run `composer require stripe/stripe-php`.');
        }

        $si = $this->client->setupIntents->create([
            'customer'             => $customerId,
            'usage'                => 'off_session',
            'payment_method_types' => ['card'],
        ]);

        Log::info('Created Stripe SetupIntent', ['id' => $si->id, 'customer' => $customerId]);

        return $si;
    }

    /**
     * Retrieve a Stripe PaymentMethod by ID.
     * Used to verify ownership and extract card details (brand, last4, expiry).
     */
    public function retrievePaymentMethod(string $paymentMethodId): object
    {
        if (! $this->client) {
            throw new \RuntimeException('Stripe SDK not available. Run `composer require stripe/stripe-php`.');
        }

        return $this->client->paymentMethods->retrieve($paymentMethodId, []);
    }

    /**
     * Detach a PaymentMethod from its Stripe Customer.
     * Called when the politician deletes a saved card.
     */
    public function detachPaymentMethod(string $paymentMethodId): void
    {
        if (! $this->client) {
            throw new \RuntimeException('Stripe SDK not available. Run `composer require stripe/stripe-php`.');
        }

        $this->client->paymentMethods->detach($paymentMethodId, []);

        Log::info('Detached Stripe PaymentMethod', ['id' => $paymentMethodId]);
    }
}
