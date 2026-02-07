<?php

namespace App\Services;

use App\Models\AdViewToken;
use App\Models\PoliticalCampaign;
use App\Models\Voter;
use App\Models\WixSite;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * Notification service that uses Wix APIs to send secure ad viewing links.
 * 
 * Wix APIs Used:
 * - Triggered Emails: Send personalized email notifications
 * - Members API: Access voter contact information
 * - Marketing API: Manage notification campaigns
 * - Push Notifications: Send browser/mobile alerts
 * 
 * Security Features:
 * - One-time use tokens
 * - Rate limiting per voter
 * - Prevents panel abuse and fraud
 * - Complete audit trail
 */
class WixNotificationService
{
    protected WixOAuthService $wixOAuth;

    public function __construct(WixOAuthService $wixOAuth)
    {
        $this->wixOAuth = $wixOAuth;
    }

    /**
     * Send ad viewing notification via Wix Triggered Email
     * 
     * @param Voter $voter The voter to notify
     * @param PoliticalCampaign $campaign The campaign ad to view
     * @param WixSite $site The Wix site instance
     * @return AdViewToken Created token for this notification
     */
    public function sendAdNotificationEmail(
        Voter $voter,
        PoliticalCampaign $campaign,
        WixSite $site
    ): AdViewToken {
        // Create secure one-time token
        $token = AdViewToken::create([
            'political_campaign_id' => $campaign->id,
            'voter_id' => $voter->id,
            'notification_method' => 'email',
            'sent_to' => $voter->email,
        ]);

        // Prepare email data
        $emailData = [
            'recipientEmail' => $voter->email,
            'recipientName' => $voter->user->name,
            'templateId' => 'ad_view_notification', // Configure in Wix Dashboard
            'variables' => [
                'voterName' => $voter->user->name,
                'campaignTitle' => $campaign->title,
                'politicianName' => $campaign->politician->name,
                'payoutAmount' => '$' . number_format($campaign->voter_payout_per_view, 2),
                'viewingUrl' => $token->getViewingUrl(),
                'expiresAt' => $token->expires_at->format('M d, Y g:i A'),
                'thumbnailUrl' => $campaign->thumbnail_url,
            ],
        ];

        try {
            // Call Wix Triggered Emails API
            $response = $this->wixOAuth->apiCall(
                $site,
                'POST',
                '/v1/triggered-emails/send',
                $emailData
            );

            $token->update([
                'sent_at' => now(),
            ]);

            Log::info('Wix ad notification email sent', [
                'voter_id' => $voter->id,
                'campaign_id' => $campaign->id,
                'token_id' => $token->id,
            ]);

            return $token;

        } catch (\Exception $e) {
            Log::error('Failed to send Wix notification email', [
                'voter_id' => $voter->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Send push notification via Wix Notifications API
     * (For mobile/browser instant alerts)
     */
    public function sendAdPushNotification(
        Voter $voter,
        PoliticalCampaign $campaign,
        WixSite $site
    ): AdViewToken {
        $token = AdViewToken::create([
            'political_campaign_id' => $campaign->id,
            'voter_id' => $voter->id,
            'notification_method' => 'push',
            'sent_to' => $voter->user->email,
        ]);

        $notificationData = [
            'recipient' => [
                'memberId' => $voter->wix_member_id, // From members API
            ],
            'notification' => [
                'title' => "New Ad Available - Earn {$campaign->voter_payout_per_view}!",
                'body' => "{$campaign->politician->name}: {$campaign->title}",
                'actionUrl' => $token->getViewingUrl(),
                'icon' => $campaign->thumbnail_url,
                'badge' => asset('images/badge.png'),
            ],
            'options' => [
                'requireInteraction' => true, // User must click to dismiss
                'tag' => 'ad_view_' . $campaign->id, // Prevents duplicate notifications
            ],
        ];

        try {
            $response = $this->wixOAuth->apiCall(
                $site,
                'POST',
                '/v1/notifications/send',
                $notificationData
            );

            $token->update(['sent_at' => now()]);

            return $token;

        } catch (\Exception $e) {
            Log::error('Failed to send Wix push notification', [
                'voter_id' => $voter->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Send SMS notification via Wix Marketing API
     * (Requires Wix site to have SMS credits)
     */
    public function sendAdSmsNotification(
        Voter $voter,
        PoliticalCampaign $campaign,
        WixSite $site
    ): ?AdViewToken {
        if (!$voter->phone) {
            Log::warning('Cannot send SMS - voter has no phone number', [
                'voter_id' => $voter->id,
            ]);
            return null;
        }

        $token = AdViewToken::create([
            'political_campaign_id' => $campaign->id,
            'voter_id' => $voter->id,
            'notification_method' => 'sms',
            'sent_to' => $voter->phone,
        ]);

        $smsData = [
            'recipient' => [
                'phoneNumber' => $voter->phone,
            ],
            'message' => "🎬 New political ad available! Watch & earn ${campaign->voter_payout_per_view}. " .
                        "{$campaign->politician->name}: {$campaign->title}. " .
                        "View: {$token->getViewingUrl()} (expires in 24h)",
        ];

        try {
            $response = $this->wixOAuth->apiCall(
                $site,
                'POST',
                '/v1/marketing/sms/send',
                $smsData
            );

            $token->update(['sent_at' => now()]);

            return $token;

        } catch (\Exception $e) {
            Log::error('Failed to send Wix SMS notification', [
                'voter_id' => $voter->id,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - SMS is optional fallback
            return null;
        }
    }

    /**
     * Rate limiting: Check if voter can receive more ad notifications
     * Prevents notification spam and fraud
     */
    public function canSendNotification(Voter $voter, int $hoursWindow = 24, int $maxAds = 10): bool
    {
        $recentTokens = AdViewToken::where('voter_id', $voter->id)
            ->where('sent_at', '>=', now()->subHours($hoursWindow))
            ->count();

        return $recentTokens < $maxAds;
    }

    /**
     * Get all available ads for a voter (system decides what to send)
     * This prevents panel abuse - voters don't choose, we push to them
     */
    public function getAvailableAdsForVoter(Voter $voter, int $limit = 5): array
    {
        // Get campaigns voter hasn't viewed yet
        $availableCampaigns = PoliticalCampaign::where('status', 'active')
            ->where('views_completed', '<', 'total_views_requested')
            ->whereDoesntHave('viewSessions', function ($query) use ($voter) {
                $query->where('voter_id', $voter->id)
                    ->where('status', 'completed');
            })
            ->whereDoesntHave('adViewTokens', function ($query) use ($voter) {
                $query->where('voter_id', $voter->id)
                    ->where('is_used', false)
                    ->where('expires_at', '>', now());
            })
            ->limit($limit)
            ->get();

        return $availableCampaigns->toArray();
    }

    /**
     * Batch send notifications to multiple voters
     * (For daily digest or new campaign launches)
     */
    public function batchSendNotifications(
        PoliticalCampaign $campaign,
        array $voterIds,
        WixSite $site,
        string $method = 'email'
    ): array {
        $sent = [];
        $failed = [];

        foreach ($voterIds as $voterId) {
            try {
                $voter = Voter::findOrFail($voterId);

                if (!$this->canSendNotification($voter)) {
                    $failed[] = [
                        'voter_id' => $voterId,
                        'reason' => 'Rate limit exceeded',
                    ];
                    continue;
                }

                $token = match ($method) {
                    'email' => $this->sendAdNotificationEmail($voter, $campaign, $site),
                    'push' => $this->sendAdPushNotification($voter, $campaign, $site),
                    'sms' => $this->sendAdSmsNotification($voter, $campaign, $site),
                    default => throw new \InvalidArgumentException("Invalid notification method: {$method}"),
                };

                $sent[] = [
                    'voter_id' => $voterId,
                    'token_id' => $token->id,
                ];

            } catch (\Exception $e) {
                $failed[] = [
                    'voter_id' => $voterId,
                    'reason' => $e->getMessage(),
                ];
            }
        }

        Log::info('Batch notification send completed', [
            'campaign_id' => $campaign->id,
            'sent_count' => count($sent),
            'failed_count' => count($failed),
        ]);

        return [
            'sent' => $sent,
            'failed' => $failed,
        ];
    }

    /**
     * Create Wix Automation for automatic ad distribution
     * Sets up workflow: New Campaign → Auto-notify eligible voters
     */
    public function createAdDistributionAutomation(WixSite $site): array
    {
        $automationData = [
            'name' => 'Auto-Distribute Political Ads',
            'description' => 'Automatically notify voters when new ads are available',
            'trigger' => [
                'type' => 'webhook',
                'event' => 'campaign.created',
                'url' => route('api.wix.automation.trigger'),
            ],
            'actions' => [
                [
                    'type' => 'send_email',
                    'template' => 'ad_view_notification',
                    'delay' => 0, // Send immediately
                ],
            ],
            'filters' => [
                'active_voters_only' => true,
                'respect_rate_limits' => true,
                'max_daily_notifications' => 10,
            ],
        ];

        return $this->wixOAuth->apiCall(
            $site,
            'POST',
            '/v1/automations/create',
            $automationData
        );
    }
}
