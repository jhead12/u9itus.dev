<?php

namespace App\Http\Controllers\Wix;

use App\Http\Controllers\Controller;
use App\Services\WixOAuthService;
use App\Services\WixWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives inbound webhooks from Wix (app installed, removed, member events).
 *
 * All webhooks MUST include a valid X-Wix-Signature header when
 * a webhook secret is configured in the environment.
 */
class WebhookController extends Controller
{
    public function __construct(
        protected WixWebhookService $webhookService,
        protected WixOAuthService $wixOAuth,
    ) {}

    /**
     * Single endpoint that handles all Wix webhook events.
     * Supports both JWT-signed webhooks (preferred) and HMAC-signed webhooks (legacy).
     */
    public function handle(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $eventType = null;
        $data = [];
        $instanceId = null;

        // ── Try JWT verification first (modern Wix webhooks) ──
        $jwtData = $this->wixOAuth->verifyWebhookJwt($rawBody);
        
        if ($jwtData) {
            // JWT verification successful
            $eventType = $jwtData['eventType'];
            $instanceId = $jwtData['instanceId'];
            $data = [
                'eventType' => $eventType,
                'instanceId' => $instanceId,
                'data' => $jwtData['data'],
            ];
            
            Log::info("Wix webhook received (JWT): {$eventType}", [
                'instanceId' => $instanceId,
            ]);
            
        } else {
            // ── Fall back to HMAC signature verification (legacy) ──
            $signature = $request->header('X-Wix-Signature');

            if (config('wix.webhook_secret') && !$signature) {
                Log::warning('Wix webhook missing signature header');
                return response()->json(['error' => 'Missing signature'], 401);
            }

            if (config('wix.webhook_secret') && !$this->wixOAuth->verifyWebhookSignature($rawBody, $signature)) {
                Log::warning('Wix webhook signature verification failed');
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // Extract data from request body (legacy format)
            $eventType = $request->input('eventType') ?? $request->input('event');
            $data = $request->all();
            
            Log::info("Wix webhook received (HMAC): {$eventType}");
        }

        if (!$eventType) {
            Log::warning('Wix webhook received without eventType', ['data' => $data]);
            return response()->json(['error' => 'Missing eventType'], 400);
        }

        try {
            $this->webhookService->handle($eventType, $data);
        } catch (\Throwable $e) {
            Log::error("Wix webhook processing error: {$e->getMessage()}", [
                'eventType' => $eventType,
                'data'      => $data,
            ]);
        }

        // Always return 200 so Wix doesn't retry
        return response()->json(['status' => 'ok']);
    }
}
