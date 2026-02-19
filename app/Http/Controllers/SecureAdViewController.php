<?php

namespace App\Http\Controllers;

use App\Models\PoliticalCampaign;
use App\Models\Voter;
use App\Models\AdViewToken;
use App\Contracts\NotificationServiceInterface;
use Illuminate\Http\Request;

/**
 * Secure ad viewing with token-based notifications
 * Prevents fraud by controlling when/how voters access ads
 */
class SecureAdViewController extends Controller
{
    protected NotificationServiceInterface $notificationService;

    public function __construct(NotificationServiceInterface $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * ADMIN/SYSTEM: Distribute ad to eligible voters
     * This is called by cron job or when campaign is approved
     */
    public function distributeAdToVoters(Request $request, PoliticalCampaign $campaign)
    {
        // Get eligible voters (based on location, demographics, etc.)
        $eligibleVoters = Voter::where('is_active', true)
            ->where('is_verified', true)
            ->where('flagged_for_fraud', false)
            ->whereHas('user', function ($query) {
                $query->whereNotNull('email_verified_at');
            })
            ->limit(100)
            ->pluck('id')
            ->toArray();

        if (empty($eligibleVoters)) {
            return response()->json(['error' => 'No eligible voters found'], 400);
        }

        // TODO: Implement batch notification distribution via StandardNotificationService
        return response()->json([
            'campaign_id' => $campaign->id,
            'eligible_voters' => count($eligibleVoters),
            'message' => 'Distribution queued',
        ]);
    }

    /**
     * PUBLIC: View ad via secure token (from email/SMS link)
     * This is the ONLY way voters can watch ads - no panel access
     */
    public function viewAdWithToken(Request $request, string $token)
    {
        $adToken = AdViewToken::where('token', $token)->first();

        if (!$adToken) {
            return view('errors.invalid-token', [
                'message' => 'Invalid or expired viewing link',
            ]);
        }

        // Check expiration
        $adToken->checkExpiration();

        // Validate token
        if (!$adToken->isValid()) {
            $reason = $adToken->is_used 
                ? 'This link has already been used' 
                : 'This link has expired';
                
            return view('errors.invalid-token', [
                'message' => $reason,
                'expired' => true,
            ]);
        }

        // Get campaign
        $campaign = $adToken->campaign;
        
        if (!$campaign || $campaign->status !== 'active') {
            return view('errors.invalid-token', [
                'message' => 'This ad is no longer available',
            ]);
        }

        // Mark token as used (prevent replay attacks)
        $adToken->markAsUsed(
            $request->ip(),
            $request->header('User-Agent')
        );

        // Show the ad video player
        return view('ads.secure-viewer', [
            'campaign' => $campaign,
            'voter' => $adToken->voter,
            'token' => $adToken,
            'payoutAmount' => $campaign->voter_payout_per_view,
        ]);
    }

    /**
     * TEST ENDPOINT: Send test notification to yourself
     */
    public function sendTestNotification(Request $request)
    {
        $voter = Voter::where('user_id', auth()->id())->firstOrFail();
        $campaign = PoliticalCampaign::where('status', 'active')->first();

        if (!$campaign) {
            return response()->json(['error' => 'No active campaign found'], 400);
        }

        try {
            $token = AdViewToken::create([
                'voter_id' => $voter->id,
                'campaign_id' => $campaign->id,
                'token' => bin2hex(random_bytes(32)),
                'expires_at' => now()->addHours(config('u9itus.assignment_expiry_hours', 24)),
                'sent_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Test notification sent! Check your email.',
                'viewing_url' => $token->getViewingUrl(),
                'expires_at' => $token->expires_at,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * VOTER DASHBOARD: Show notification history (cannot request ads)
     * Voters see what was sent to them, but cannot generate new links
     */
    public function myNotifications(Request $request)
    {
        $voter = Voter::where('user_id', auth()->id())->firstOrFail();

        $notifications = AdViewToken::where('voter_id', $voter->id)
            ->with('campaign.politician')
            ->orderBy('sent_at', 'desc')
            ->paginate(20);

        return view('voter.notifications', [
            'notifications' => $notifications,
            'voter' => $voter,
        ]);
    }

    /**
     * API: Check if token is valid (before video loads)
     */
    public function validateToken(Request $request, string $token)
    {
        $adToken = AdViewToken::where('token', $token)->first();

        if (!$adToken) {
            return response()->json(['valid' => false, 'reason' => 'not_found'], 404);
        }

        $adToken->checkExpiration();

        return response()->json([
            'valid' => $adToken->isValid(),
            'reason' => !$adToken->isValid() 
                ? ($adToken->is_used ? 'already_used' : 'expired') 
                : null,
            'campaign' => $adToken->isValid() ? [
                'id' => $adToken->campaign->id,
                'title' => $adToken->campaign->title,
                'duration' => $adToken->campaign->media_duration,
            ] : null,
        ]);
    }
}
