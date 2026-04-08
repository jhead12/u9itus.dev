<?php

namespace App\Services;

use App\Exceptions\CashAppPayoutException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * Cash App outbound payout service.
 *
 * Executes the network create-payment endpoint and returns immutable
 * execution reference plus processor fee for accounting.
 */
class CashAppPayoutService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $merchantId;
    protected string $paymentsEndpoint;
    protected string $defaultGrantId;
    protected string $region;
    protected string $signature;
    protected string $userAgent;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.cashapp.base_url', ''), '/');
        $this->apiKey = (string) config('services.cashapp.api_key', '');
        $this->merchantId = (string) config('services.cashapp.merchant_id', '');
        $this->paymentsEndpoint = (string) config('services.cashapp.payments_endpoint', '/network/v1/payments');
        $this->defaultGrantId = (string) config('services.cashapp.default_grant_id', '');
        $this->region = (string) config('services.cashapp.region', 'US');
        $this->signature = (string) config('services.cashapp.signature', '');
        $this->userAgent = (string) config('services.cashapp.user_agent', 'u9itus-cashapp/1.0');
        $this->timeout = (int) config('services.cashapp.timeout', 30);
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== ''
            && $this->apiKey !== ''
            && $this->merchantId !== ''
            && $this->region !== ''
            && $this->signature !== '';
    }

    /**
     * Execute outbound payout for one voter via Cash App.
     *
     * @return array{reference:string,fee:float,payment:array}
     */
    public function sendPayout(string $cashappTag, float $amount, string $referenceId, ?string $note = null, ?string $grantId = null): array
    {
        if (! $this->isConfigured()) {
            throw new CashAppPayoutException('Cash App credentials are not configured.');
        }

        $tag = ltrim(trim($cashappTag), '$');
        if ($tag === '') {
            throw new \InvalidArgumentException('Cash App cashtag is required.');
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Cash App payout amount must be greater than zero.');
        }

        $amountCents = (int) round($amount * 100);
        $resolvedGrantId = (string) ($grantId ?? $this->defaultGrantId);
        if ($resolvedGrantId === '') {
            throw new CashAppPayoutException('Cash App payout failed: grant id is required. Configure CASHAPP_DEFAULT_GRANT_ID or provide a grant id.');
        }

        $paymentPayload = [
            'idempotency_key' => $referenceId,
            'payment' => [
                'amount' => $amountCents,
                'currency' => 'USD',
                'merchant_id' => $this->merchantId,
                'grant_id' => $resolvedGrantId,
                'reference_id' => $referenceId,
                'capture' => true,
                'metadata' => [
                    'cashapp_tag' => $tag,
                    'note' => (string) ($note ?? 'U9itus viewer earnings payout'),
                ],
                'enrichments' => [
                    'initiation' => [
                        'actor' => 'MERCHANT',
                    ],
                ],
            ],
        ];

        $paymentResponse = $this->postJson($this->paymentsEndpoint, $paymentPayload);

        $executionReference = (string) (
            data_get($paymentResponse, 'payment.id')
            ?? data_get($paymentResponse, 'id')
            ?? $referenceId
        );

        $processorFee = $this->extractFeeAmount($paymentResponse);

        Log::info('Cash App payout created', [
            'reference' => $executionReference,
            'cashapp_tag' => $tag,
            'amount' => $amount,
            'fee' => $processorFee,
        ]);

        return [
            'reference' => $executionReference,
            'fee' => $processorFee,
            'payment' => $paymentResponse,
        ];
    }

    protected function postJson(string $endpoint, array $payload): array
    {
        $client = new Client(['timeout' => $this->timeout]);
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        try {
            $response = $client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-Region' => $this->region,
                    'X-Signature' => $this->signature,
                    'User-Agent' => $this->userAgent,
                ],
                'json' => $payload,
            ]);
        } catch (RequestException $e) {
            $body = $e->hasResponse() ? (string) $e->getResponse()?->getBody() : null;
            Log::error('Cash App API request failed', [
                'endpoint' => $url,
                'status' => $e->getCode(),
                'body' => $body,
            ]);

            throw $e;
        }

        $decoded = json_decode((string) $response->getBody(), true);
        if (! is_array($decoded)) {
            throw new CashAppPayoutException('Cash App API returned an invalid JSON payload.');
        }

        return $decoded;
    }

    protected function extractFeeAmount(array $payload): float
    {
        $cents = data_get($payload, 'payment.processing_fee_money.amount')
            ?? data_get($payload, 'capture.processing_fee_money.amount')
            ?? data_get($payload, 'processing_fee_money.amount')
            ?? data_get($payload, 'fees.total_money.amount')
            ?? data_get($payload, 'fee_money.amount');

        if (is_numeric($cents)) {
            return round(((float) $cents) / 100, 2);
        }

        $decimal = data_get($payload, 'processing_fee')
            ?? data_get($payload, 'fee')
            ?? data_get($payload, 'payment.fee_amount')
            ?? data_get($payload, 'payment.fee')
            ?? data_get($payload, 'capture.fee');

        return is_numeric($decimal) ? round((float) $decimal, 2) : 0.00;
    }
}
