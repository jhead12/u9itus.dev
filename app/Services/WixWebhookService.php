<?php

namespace App\Services;

use App\Models\WixSite;
use Illuminate\Support\Facades\Log;

/**
 * Processes inbound Wix webhooks (app installed, uninstalled, member events, etc.)
 */
class WixWebhookService
{
    public function __construct(
        protected WixOAuthService $oauthService,
    ) {}

    /**
     * Route an incoming webhook to the correct handler.
     */
    public function handle(string $eventType, array $payload): void
    {
        match ($eventType) {
            'AppInstalled'   => $this->onAppInstalled($payload),
            'AppRemoved'     => $this->onAppRemoved($payload),
            'MemberSignedUp' => $this->onMemberSignedUp($payload),
            'MemberDeleted'  => $this->onMemberDeleted($payload),
            default          => Log::info("Unhandled Wix webhook: {$eventType}", $payload),
        };
    }

    protected function onAppInstalled(array $payload): void
    {
        $instanceId = $payload['instanceId'] ?? $payload['data']['instanceId'] ?? null;
        if (!$instanceId) {
            Log::warning('AppInstalled webhook missing instanceId', $payload);
            return;
        }

        $site = WixSite::where('instance_id', $instanceId)->first();
        if ($site) {
            $site->update([
                'is_active'      => true,
                'installed_at'   => now(),
                'uninstalled_at' => null,
            ]);
        }

        Log::info("Wix app installed for instance {$instanceId}");
    }

    protected function onAppRemoved(array $payload): void
    {
        $instanceId = $payload['instanceId'] ?? $payload['data']['instanceId'] ?? null;
        if (!$instanceId) {
            return;
        }

        WixSite::where('instance_id', $instanceId)->update([
            'is_active'      => false,
            'uninstalled_at' => now(),
        ]);

        Log::info("Wix app removed for instance {$instanceId}");
    }

    protected function onMemberSignedUp(array $payload): void
    {
        // Future: auto-create voter or politician profile when a Wix member signs up
        Log::info('Wix member signed up', $payload);
    }

    protected function onMemberDeleted(array $payload): void
    {
        Log::info('Wix member deleted', $payload);
    }
}
