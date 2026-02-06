<?php

namespace App\Http\Controllers\Wix;

use App\Http\Controllers\Controller;
use App\Services\WixWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives inbound webhooks from Wix (app installed, removed, member events).
 */
class WebhookController extends Controller
{
    public function __construct(
        protected WixWebhookService $webhookService,
    ) {}

    /**
     * Single endpoint that handles all Wix webhook events.
     */
    public function handle(Request $request)
    {
        // Verify signature if webhook secret is configured
        $signature = $request->header('X-Wix-Signature');
        if ($signature && config('wix.webhook_secret')) {
            $payload = $request->getContent();
            $service = app(\App\Services\WixOAuthService::class);

            if (!$service->verifyWebhookSignature($payload, $signature)) {
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
