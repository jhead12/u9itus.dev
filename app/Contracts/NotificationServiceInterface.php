<?php

namespace App\Contracts;

use App\Models\User;

/**
 * Notification Service Interface
 * 
 * Platform-agnostic interface for sending notifications to users.
 * Implementations exist for both Wix and standalone platforms.
 */
interface NotificationServiceInterface
{
    /**
     * Send an email notification to a user.
     *
     * @param User $user
     * @param string $subject
     * @param string $message
     * @param array $data Additional data for email template
     * @return bool
     */
    public function sendEmail(User $user, string $subject, string $message, array $data = []): bool;

    /**
     * Send an SMS notification to a user.
     *
     * @param User $user
     * @param string $message
     * @return bool
     */
    public function sendSMS(User $user, string $message): bool;

    /**
     * Send a push notification to a user.
     *
     * @param User $user
     * @param string $title
     * @param string $message
     * @param array $data Additional data for the notification
     * @return bool
     */
    public function sendPush(User $user, string $title, string $message, array $data = []): bool;

    /**
     * Send an ad view notification with secure token.
     *
     * @param User $user
     * @param string $token
     * @param string $campaignTitle
     * @param float $earningsAmount
     * @return bool
     */
    public function sendAdNotification(User $user, string $token, string $campaignTitle, float $earningsAmount): bool;

    /**
     * Check if a notification channel is available for the user.
     *
     * @param User $user
     * @param string $channel (email, sms, push)
     * @return bool
     */
    public function isChannelAvailable(User $user, string $channel): bool;
}
