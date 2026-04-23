<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * PayPal Payouts (v1/payments/payouts) service.
 *
 * Sends a single batch payout request containing one item per voter. PayPal
 * processes each item individually, so partial failures are surfaced per-item
 * in the API response rather than failing the whole batch.
 */
class PayPalPayoutService
{
    protected string $baseUrl;
    protected string $clientId;
    protected string $clientSecret;
    protected string $webhookId;

    public function __construct()
    {
        $sandbox = config('services.paypal.sandbox', true);

        $this->baseUrl       = $sandbox
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
        $this->clientId      = config('services.paypal.client_id', '');
        $this->clientSecret  = config('services.paypal.client_secret', '');
        $this->webhookId     = (string) config('services.paypal.webhook_id', '');
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }

    /**
     * Obtain a short-lived OAuth 2.0 Bearer token from PayPal.
     */
    protected function getAccessToken(): string
    {
        $client = new Client(['timeout' => 10]);

        $response = $client->post("{$this->baseUrl}/v1/oauth2/token", [
            'auth'        => [$this->clientId, $this->clientSecret],
            'form_params' => ['grant_type' => 'client_credentials'],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (empty($data['access_token'])) {
            throw new \RuntimeException('PayPal did not return an access token.');
        }

        return $data['access_token'];
    }

    /**
     * Send a batch payout.
     *
     * $items must be an array of:
     *   [ 'email' => string, 'amount' => float, 'note' => string, 'sender_item_id' => string ]
     *
     * Returns the raw PayPal Payouts response (batch_header + links).
     *
     * @throws \RuntimeException when credentials are missing.
     * @throws \GuzzleHttp\Exception\RequestException on HTTP errors.
     */
    public function sendBatchPayout(string $batchId, array $items): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException(
                'PayPal credentials not configured. Set PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET.'
            );
        }

        if (empty($items)) {
            throw new \InvalidArgumentException('Payout items list is empty.');
        }

        $accessToken = $this->getAccessToken();

        $payoutItems = array_map(function (array $item) {
            return [
                'recipient_type' => 'EMAIL',
                'amount'         => [
                    'value'    => number_format((float) $item['amount'], 2, '.', ''),
                    'currency' => 'USD',
                ],
                'note'           => $item['note'] ?? 'U9itus viewer earnings payout',
                'sender_item_id' => $item['sender_item_id'] ?? uniqid('voter_'),
                'receiver'       => $item['email'],
            ];
        }, $items);

        $client = new Client(['timeout' => 30]);

        try {
            $response = $client->post("{$this->baseUrl}/v1/payments/payouts", [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'sender_batch_header' => [
                        'sender_batch_id' => $batchId,
                        'email_subject'   => 'U9itus — Your earnings have arrived!',
                        'email_message'   => 'You have received a payout from U9itus for your recent ad-viewing activity. Thank you for participating!',
                    ],
                    'items' => $payoutItems,
                ],
            ]);
        } catch (RequestException $e) {
            $body = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null;
            Log::error('PayPal Payouts API error', ['status' => $e->getCode(), 'body' => $body]);
            throw $e;
        }

        $result = json_decode($response->getBody()->getContents(), true);

        Log::info('PayPal batch payout submitted', [
            'batch_id'   => $batchId,
            'item_count' => count($payoutItems),
            'paypal_batch_id' => $result['batch_header']['payout_batch_id'] ?? null,
        ]);

        return $result;
    }

    /**
     * Get payout batch status and item-level statuses from PayPal.
     */
    public function getBatchPayoutStatus(string $payoutBatchId): array
    {
        if ($payoutBatchId === '') {
            throw new \InvalidArgumentException('PayPal payout batch reference is required.');
        }

        $accessToken = $this->getAccessToken();
        $client = new Client(['timeout' => 20]);

        try {
            $response = $client->get("{$this->baseUrl}/v1/payments/payouts/{$payoutBatchId}", [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'query' => [
                    'page_size' => 100,
                    'page' => 1,
                    'total_required' => true,
                ],
            ]);
        } catch (RequestException $e) {
            $body = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null;
            Log::error('PayPal batch status API error', ['status' => $e->getCode(), 'body' => $body]);
            throw $e;
        }

        return json_decode($response->getBody()->getContents(), true) ?? [];
    }

    /**
     * Verify webhook signatures with PayPal. Can be bypassed in local/dev when webhook ID is missing.
     */
    public function verifyWebhookSignature(Request $request, array $payload): bool
    {
        if ($this->webhookId === '') {
            return ! app()->environment('production');
        }

        $accessToken = $this->getAccessToken();
        $client = new Client(['timeout' => 20]);

        $body = [
            'auth_algo' => (string) $request->header('Paypal-Auth-Algo', ''),
            'cert_url' => (string) $request->header('Paypal-Cert-Url', ''),
            'transmission_id' => (string) $request->header('Paypal-Transmission-Id', ''),
            'transmission_sig' => (string) $request->header('Paypal-Transmission-Sig', ''),
            'transmission_time' => (string) $request->header('Paypal-Transmission-Time', ''),
            'webhook_id' => $this->webhookId,
            'webhook_event' => $payload,
        ];

        try {
            $response = $client->post("{$this->baseUrl}/v1/notifications/verify-webhook-signature", [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);
        } catch (RequestException $e) {
            Log::warning('PayPal webhook verification failed', [
                'status' => $e->getCode(),
                'message' => $e->getMessage(),
            ]);
            return false;
        }

        $result = json_decode($response->getBody()->getContents(), true);
        return strtoupper((string) ($result['verification_status'] ?? '')) === 'SUCCESS';
    }
}
