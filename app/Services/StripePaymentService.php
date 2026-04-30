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
                Log::warning('Stripe secret key not configured (STRIPE_SECRET_KEY env var missing). Payments are disabled.');
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

        $pi = $this->client->paymentIntents->create($params);

        Log::info('Created Stripe PaymentIntent', ['id' => $pi->id, 'amount' => $amount]);

        return $pi;
    }

    /**
     * Retrieve or create a Stripe Customer for a politician.
     *
     * Saves `stripe_customer_id` back to the politician row so subsequent
     * calls are instant (idempotent).
     */
    public function ensureCustomer(Politician $politician): ?string
    {
        if (! $this->client) {
            Log::warning('Stripe SDK not available — cannot ensure customer.');
            return null;
        }

        if (! empty($politician->stripe_customer_id)) {
            try {
                // Verify the saved customer still exists in the active Stripe account.
                $this->client->customers->retrieve($politician->stripe_customer_id, []);
                return $politician->stripe_customer_id;
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                // Common when a customer id belongs to another Stripe account/mode
                // or was deleted; recreate and persist a fresh customer id.
                Log::warning('Stored Stripe customer missing, recreating customer.', [
                    'politician_id'      => $politician->id,
                    'stripe_customer_id' => $politician->stripe_customer_id,
                    'error'              => $e->getMessage(),
                ]);
            }
        }

        // Resolve the best available email from the related User.
        $email = optional($politician->user)->email ?? null;

        $customer = $this->client->customers->create([
            'name'     => $politician->full_name,
            'email'    => $email,
            'metadata' => [
                'politician_id'   => $politician->id,
                'politician_uuid' => $politician->uuid,
            ],
        ]);

        $politician->stripe_customer_id = $customer->id;
        $politician->saveQuietly();

        Log::info('Created Stripe Customer for politician', [
            'politician_id'      => $politician->id,
            'stripe_customer_id' => $customer->id,
        ]);

        return $customer->id;
    }

    /**
     * Verify webhook signature (returns payload array on success).
     */
    public function parseWebhook(string $payload, string $sigHeader)
    {
        if (! $this->client) {
            // Fail closed — SDK must be present to verify webhooks
            if (! app()->environment('local', 'testing')) {
                throw new \RuntimeException(
                    'Stripe SDK is not installed. Cannot verify webhook signatures in non-local environments.'
                );
            }
            Log::warning('Stripe SDK not installed; webhook events will not be verified (dev/test only).');
            return json_decode($payload, true);
        }

        $secret = config('services.stripe.webhook_secret');
        if (empty($secret)) {
            // Fail closed — no secret means we cannot trust the payload
            if (! app()->environment('local', 'testing')) {
                throw new \RuntimeException(
                    'Stripe webhook secret is not configured (STRIPE_WEBHOOK_SECRET). ' .
                    'Cannot accept webhook payloads without signature verification.'
                );
            }
            Log::warning('Stripe webhook secret not configured (services.stripe.webhook_secret). Signature check skipped (dev/test only).');
            return json_decode($payload, true);
        }

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
            return $event;
        } catch (\UnexpectedValueException $e) {
            Log::error('Stripe webhook payload invalid: ' . $e->getMessage());
            throw $e;
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Stripe webhook signature verification failed: ' . $e->getMessage());
            throw $e;
        }
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
