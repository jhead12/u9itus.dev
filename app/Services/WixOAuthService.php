<?php

namespace App\Services;

use App\Models\WixSite;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Handles Wix OAuth flow, token refresh, and API calls.
 *
 * Wix app installation flow:
 *   1. User clicks "Add to Site" in Wix Marketplace
 *   2. Wix redirects to our app URL with a `token`
 *   3. We exchange that token for an authorization code
 *   4. We exchange the auth code for access + refresh tokens
 *   5. We store those tokens in `wix_sites` and use them for API calls
 */
class WixOAuthService
{
    protected string $appId;
    protected string $appSecret;
    protected string $tokenUrl;
    protected string $apiBaseUrl;

    public function __construct()
    {
        $this->appId      = config('wix.app_id');
        $this->appSecret  = config('wix.app_secret');
        $this->tokenUrl   = config('wix.token_url');
        $this->apiBaseUrl  = config('wix.api_base_url');
    }

    /**
     * Build the consent URL that Wix redirects the site owner to.
     */
    public function getConsentUrl(string $token): string
    {
        return config('wix.oauth_url') . '?' . http_build_query([
            'token'       => $token,
            'appId'       => $this->appId,
            'redirectUrl' => config('wix.app_url') . config('wix.redirect_url'),
        ]);
    }

    /**
     * Exchange the authorization code for access + refresh tokens.
     */
    public function exchangeCodeForTokens(string $authCode): array
    {
        $response = Http::post($this->tokenUrl, [
            'grant_type'    => 'authorization_code',
            'client_id'     => $this->appId,
            'client_secret' => $this->appSecret,
            'code'          => $authCode,
        ]);

        if ($response->failed()) {
            Log::error('Wix token exchange failed', ['body' => $response->body()]);
            throw new \RuntimeException('Failed to exchange Wix auth code for tokens');
        }

        return $response->json();
    }

    /**
     * Refresh an expired access token.
     */
    public function refreshAccessToken(WixSite $site): WixSite
    {
        $response = Http::post($this->tokenUrl, [
            'grant_type'    => 'refresh_token',
            'client_id'     => $this->appId,
            'client_secret' => $this->appSecret,
            'refresh_token' => $site->refresh_token,
        ]);

        if ($response->failed()) {
            Log::error('Wix token refresh failed', [
                'instance_id' => $site->instance_id,
                'body'        => $response->body(),
            ]);
            throw new \RuntimeException('Failed to refresh Wix token');
        }

        $data = $response->json();

        $site->update([
            'access_token'    => $data['access_token'],
            'refresh_token'   => $data['refresh_token'] ?? $site->refresh_token,
            'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
        ]);

        return $site->fresh();
    }

    /**
     * Make an authenticated API call to Wix on behalf of a site.
     */
    public function apiCall(WixSite $site, string $method, string $endpoint, array $data = []): array
    {
        if ($site->tokenExpired()) {
            $site = $this->refreshAccessToken($site);
        }

        $url = $this->apiBaseUrl . '/' . ltrim($endpoint, '/');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $site->access_token,
        ])->{$method}($url, $data);

        if ($response->failed()) {
            Log::error('Wix API call failed', [
                'endpoint' => $endpoint,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);
        }

        return $response->json() ?? [];
    }

    /**
     * Verify a Wix webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secret = config('wix.webhook_secret');
        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Decode and verify the Wix instance parameter (JWT-like, base64).
     */
    public function decodeInstance(string $instance): ?array
    {
        $parts = explode('.', $instance);
        if (count($parts) !== 2) {
            return null;
        }

        [$hash, $payload] = $parts;
        $expectedHash = hash_hmac('sha256', $payload, $this->appSecret, true);
        $expectedHash = rtrim(strtr(base64_encode($expectedHash), '+/', '-_'), '=');

        if (!hash_equals($expectedHash, $hash)) {
            Log::warning('Wix instance signature mismatch');
            return null;
        }

        return json_decode(base64_decode($payload), true);
    }

    /**
     * Store or update site tokens after successful OAuth.
     */
    public function createOrUpdateSite(string $instanceId, array $tokenData): WixSite
    {
        return WixSite::updateOrCreate(
            ['instance_id' => $instanceId],
            [
                'access_token'    => $tokenData['access_token'],
                'refresh_token'   => $tokenData['refresh_token'] ?? null,
                'token_expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 3600),
                'is_active'       => true,
                'installed_at'    => now(),
                'uninstalled_at'  => null,
            ]
        );
    }
}
