<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
    public function createPaymentIntent(float $amount, string $currency = 'usd', array $metadata = [])
    {
        if (! $this->client) {
            throw new \RuntimeException('Stripe SDK not available. Run `composer require stripe/stripe-php`.');
        }

        $amountCents = (int) round($amount * 100);

        $pi = $this->client->paymentIntents->create([
            'amount' => $amountCents,
            'currency' => $currency,
            'payment_method_types' => ['card'],
            'metadata' => $metadata,
        ]);

        Log::info('Created Stripe PaymentIntent', ['id' => $pi->id, 'amount' => $amount]);

        return $pi;
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
