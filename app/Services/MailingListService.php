<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mailing List Service
 *
 * Subscribes email addresses to the configured Mailgun mailing list. Failures
 * are logged but never break the user-facing flow.
 */
class MailingListService
{
    /**
     * Add an email address to a Mailgun mailing list via the Members API.
     * Silently returns on any failure. Defaults to the general waitlist list;
     * pass $listAddress to target a different list (e.g. the map-favorites
     * digest list).
     */
    public function subscribe(string $email, string $source = 'register_closed', ?string $listAddress = null): void
    {
        $listAddress = $listAddress ?? config('services.mailgun.mailing_list');
        $apiKey      = config('services.mailgun.secret');
        $endpoint    = config('services.mailgun.endpoint', 'api.mailgun.net');

        if (! $listAddress || ! $apiKey) {
            return; // Not configured — skip silently
        }

        try {
            $response = Http::withBasicAuth('api', $apiKey)
                ->asForm()
                ->post("https://{$endpoint}/v3/lists/{$listAddress}/members", [
                    'address'    => $email,
                    'subscribed' => 'yes',
                    'upsert'     => 'yes', // update if already exists
                ]);

            if (! $response->successful()) {
                Log::warning('MailingList: Mailgun Members API error', [
                    'email'  => $email,
                    'source' => $source,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('MailingList: Mailgun Members API exception', [
                'email' => $email,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
