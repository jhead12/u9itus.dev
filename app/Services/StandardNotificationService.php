<?php

namespace App\Services;

use App\Contracts\NotificationServiceInterface;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\AdNotificationMail;

/**
 * Standard Notification Service (Standalone Platform)
 * 
 * Handles notifications using:
 * - Laravel Mail for emails
 * - Twilio for SMS (TODO: implement)
 * - Firebase Cloud Messaging for push notifications (TODO: implement)
 */
class StandardNotificationService implements NotificationServiceInterface
{
    /**
     * Send an email notification to a user.
     */
    public function sendEmail(User $user, string $subject, string $message, array $data = []): bool
    {
        try {
            Mail::to($user->email)->send(new \App\Mail\GenericMail($subject, $message, $data));
            
            Log::info('Email sent', [
                'user_id' => $user->id,
                'email' => $user->email,
                'subject' => $subject,
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Send an SMS notification to a user.
     * 
     * TODO: Implement Twilio integration
     */
    public function sendSMS(User $user, string $message): bool
    {
        // TODO: Implement Twilio SMS sending
        // For now, log and return false
        Log::warning('SMS not implemented yet', [
            'user_id' => $user->id,
            'phone' => $user->phone ?? 'N/A',
            'message' => $message,
        ]);
        
        return false;
    }

    /**
     * Send a push notification to a user.
     * 
     * TODO: Implement Firebase Cloud Messaging
     */
    public function sendPush(User $user, string $title, string $message, array $data = []): bool
    {
        // TODO: Implement Firebase push notifications
        // For now, log and return false
        Log::warning('Push notifications not implemented yet', [
            'user_id' => $user->id,
            'title' => $title,
            'message' => $message,
        ]);
        
        return false;
    }

    /**
     * Send an ad view notification with secure token.
     */
    public function sendAdNotification(User $user, string $token, string $campaignTitle, float $earningsAmount): bool
    {
        $watchUrl = route('voter.watch', ['token' => $token]);
        
        $message = "New political message available! Watch \"{$campaignTitle}\" and earn $" . number_format($earningsAmount, 2);
        
        $emailSubject = "💰 Earn $" . number_format($earningsAmount, 2) . " - New Message Available";
        
        // Try email first (primary channel for standalone)
        $emailSent = $this->sendEmail($user, $emailSubject, $message, [
            'campaign_title' => $campaignTitle,
            'earnings' => $earningsAmount,
            'watch_url' => $watchUrl,
            'token' => $token,
        ]);
        
        // If user has phone, try SMS as backup
        if ($user->phone && !$emailSent) {
            return $this->sendSMS($user, $message . " - " . $watchUrl);
        }
        
        return $emailSent;
    }

    /**
     * Check if a notification channel is available for the user.
     */
    public function isChannelAvailable(User $user, string $channel): bool
    {
        return match($channel) {
            'email' => !empty($user->email),
            'sms' => !empty($user->phone) && config('services.twilio.enabled', false),
            'push' => !empty($user->fcm_token) && config('services.firebase.enabled', false),
            default => false,
        };
    }
}
