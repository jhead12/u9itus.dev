<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Twilio SMS Service
 * 
 * Handles SMS notifications via Twilio.
 * 
 * Setup Instructions:
 * 1. Create a Twilio account at https://www.twilio.com/try-twilio
 * 2. Get your Account SID and Auth Token from the console
 * 3. Purchase a Twilio phone number (or use your trial number for testing)
 * 4. Add the following to your .env file:
 *    - TWILIO_ACCOUNT_SID=your-account-sid
 *    - TWILIO_AUTH_TOKEN=your-auth-token
 *    - TWILIO_FROM_NUMBER=+1234567890
 * 5. Users need to provide their phone number in notification preferences
 * 
 * @see https://www.twilio.com/docs/sms/api
 */
class TwilioSmsService
{
    private ?string $accountSid;
    private ?string $authToken;
    private ?string $fromNumber;

    public function __construct()
    {
        $this->accountSid = config('services.twilio.account_sid');
        $this->authToken = config('services.twilio.auth_token');
        $this->fromNumber = config('services.twilio.from_number');
    }

    /**
     * Send an SMS message to a user.
     */
    public function sendSms(User $user, string $message): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('Twilio SMS is not configured. Skipping SMS notification.', [
                'user_id' => $user->id,
            ]);
            return false;
        }

        $phoneNumber = $user->notificationPreference?->phone_number;

        if (!$phoneNumber) {
            Log::info('User does not have a phone number. Skipping SMS.', [
                'user_id' => $user->id,
            ]);
            return false;
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($this->accountSid, $this->authToken)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json", [
                    'From' => $this->fromNumber,
                    'To' => $phoneNumber,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                Log::info('SMS sent successfully', [
                    'user_id' => $user->id,
                    'to' => $phoneNumber,
                    'sid' => $response->json()['sid'] ?? null,
                ]);
                return true;
            }

            Log::error('Failed to send SMS', [
                'user_id' => $user->id,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Exception sending SMS', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check if Twilio is properly configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->accountSid) 
            && !empty($this->authToken) 
            && !empty($this->fromNumber);
    }

    /**
     * Validate a phone number format.
     */
    public function validatePhoneNumber(string $phoneNumber): bool
    {
        // Basic E.164 format validation: +[country code][number]
        // Example: +14155552671
        return preg_match('/^\+[1-9]\d{1,14}$/', $phoneNumber) === 1;
    }

    /**
     * Format a phone number to E.164 format.
     * 
     * This is a simple US number formatter. For international support,
     * consider using a library like giggsey/libphonenumber-for-php.
     */
    public function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove all non-digit characters
        $cleaned = preg_replace('/\D+/', '', $phoneNumber);

        // Add +1 prefix for US numbers if not present
        if (strlen($cleaned) === 10) {
            return '+1' . $cleaned;
        }

        if (strlen($cleaned) === 11 && substr($cleaned, 0, 1) === '1') {
            return '+' . $cleaned;
        }

        // Return as-is if already formatted or unrecognized
        return $phoneNumber;
    }
}
