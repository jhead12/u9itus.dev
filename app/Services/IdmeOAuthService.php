<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class IdmeOAuthService
{
    public function isConfigured(): bool
    {
        return (string) config('services.idme.client_id', '') !== ''
            && (string) config('services.idme.client_secret', '') !== '';
    }

    public function authorizationUrl(string $state): string
    {
        $scopes = config('services.idme.scopes', ['identity', 'email']);
        if (!is_array($scopes) || empty($scopes)) {
            $scopes = ['identity', 'email'];
        }

        $query = [
            'client_id' => (string) config('services.idme.client_id'),
            'redirect_uri' => (string) config('services.idme.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => $state,
        ];

        return rtrim((string) config('services.idme.auth_url'), '?') . '?' . http_build_query($query);
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeCodeForToken(string $code): array
    {
        $response = Http::asForm()
            ->acceptJson()
            ->timeout(15)
            ->post((string) config('services.idme.token_url'), [
                'grant_type' => 'authorization_code',
                'client_id' => (string) config('services.idme.client_id'),
                'client_secret' => (string) config('services.idme.client_secret'),
                'redirect_uri' => (string) config('services.idme.redirect_uri'),
                'code' => $code,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Id.me token exchange failed: ' . $response->body());
        }

        $data = $response->json();

        if (!is_array($data) || empty($data['access_token'])) {
            throw new RuntimeException('Id.me token exchange returned an invalid payload.');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchAttributes(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(15)
            ->get((string) config('services.idme.attributes_url'));

        if ($response->failed()) {
            throw new RuntimeException('Id.me attributes request failed: ' . $response->body());
        }

        $data = $response->json();

        if (!is_array($data)) {
            throw new RuntimeException('Id.me attributes response is not a JSON object.');
        }

        return $data;
    }
}
