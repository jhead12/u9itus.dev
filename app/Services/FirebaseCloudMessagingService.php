<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Firebase Cloud Messaging Service
 * 
 * Handles push notifications via Firebase Cloud Messaging (FCM).
 * 
 * Setup Instructions:
 * 1. Create a Firebase project at https://console.firebase.google.com
 * 2. Go to Project Settings > Service Accounts
 * 3. Generate a new private key (JSON file)
 * 4. Add the following to your .env file:
 *    - FCM_PROJECT_ID=your-project-id
 *    - FCM_CREDENTIALS_PATH=/path/to/service-account.json
 * 5. Add Firebase SDK to your frontend:
 *    - npm install firebase
 *    - Initialize Firebase in your app
 *    - Request notification permission
 *    - Get FCM token and store via /api/notification-preferences/fcm-token
 * 
 * @see https://firebase.google.com/docs/cloud-messaging
 */
class FirebaseCloudMessagingService
{
    private ?string $projectId;
    private ?string $credentialsPath;
    private ?string $accessToken = null;

    public function __construct()
    {
        $this->projectId = config('services.fcm.project_id');
        $this->credentialsPath = config('services.fcm.credentials_path');
    }

    /**
     * Send a push notification to a user.
     */
    public function sendNotification(User $user, string $title, string $body, ?array $data = []): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('Firebase Cloud Messaging is not configured. Skipping push notification.', [
                'user_id' => $user->id,
                'title' => $title,
            ]);
            return false;
        }

        $fcmToken = $user->notificationPreference?->fcm_token;

        if (!$fcmToken) {
            Log::info('User does not have an FCM token. Skipping push notification.', [
                'user_id' => $user->id,
            ]);
            return false;
        }

        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", [
                'message' => [
                    'token' => $fcmToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $data,
                    'webpush' => [
                        'fcm_options' => [
                            'link' => $data['action_url'] ?? url('/'),
                        ],
                    ],
                ],
            ]);

            if ($response->successful()) {
                Log::info('Push notification sent successfully', [
                    'user_id' => $user->id,
                    'title' => $title,
                ]);
                return true;
            }

            Log::error('Failed to send push notification', [
                'user_id' => $user->id,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Exception sending push notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check if FCM is properly configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->projectId) 
            && !empty($this->credentialsPath) 
            && file_exists($this->credentialsPath);
    }

    /**
     * Get OAuth 2.0 access token for FCM.
     * 
     * This uses Google Service Account credentials to obtain an access token.
     * Tokens are cached for 1 hour (Firebase tokens expire after 1 hour).
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $credentials = json_decode(file_get_contents($this->credentialsPath), true);

        // Create JWT for Google OAuth 2.0
        $now = time();
        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $payload = json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ]);

        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode($payload);
        $signature = '';
        openssl_sign(
            "$base64UrlHeader.$base64UrlPayload",
            $signature,
            $credentials['private_key'],
            'SHA256'
        );
        $base64UrlSignature = $this->base64UrlEncode($signature);

        $jwt = "$base64UrlHeader.$base64UrlPayload.$base64UrlSignature";

        // Exchange JWT for access token
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        $data = $response->json();
        $this->accessToken = $data['access_token'] ?? null;

        return $this->accessToken;
    }

    /**
     * Base64 URL encode.
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
