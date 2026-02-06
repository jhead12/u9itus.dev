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
     */
    public function handle(Request $request): JsonResponse
    {
        // ── Signature verification (mandatory when secret is configured) ──
        if (config('wix.webhook_secret')) {
            $signature = $request->header('X-Wix-Signature');

            if (!$signature) {
                Log::warning('Wix webhook missing signature header');
                return response()->json(['error' => 'Missing signature'], 401);
            }

            if (!$this->wixOAuth->verifyWebhookSignature($request->getContent(), $signature)) {
                Log::warning('Wix webhook signature verification failed');
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $eventType = $request->input('eventType') ?? $request->input('event');
        $data      = $request->all();

        if (!$eventType) {
            Log::warning('Wix webhook received without eventType', $data);
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
