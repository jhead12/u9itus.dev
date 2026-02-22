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
            return $politician->stripe_customer_id;
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
            Log::warning('Stripe SDK not installed; webhook events will not be verified.');
            return json_decode($payload, true);
        }

        $secret = config('services.stripe.webhook_secret');
        if (empty($secret)) {
            Log::warning('Stripe webhook secret not configured (services.stripe.webhook_secret).');
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
}
