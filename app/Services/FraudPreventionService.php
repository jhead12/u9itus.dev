<?php

namespace App\Services;

use App\Enums\ViewPaymentStatus;
use App\Enums\ViewSessionStatus;
use App\Models\ViewSession;
use App\Models\Voter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Fraud detection & prevention for view sessions.
 *
 * Checks device fingerprinting, rate limits, IP anomalies, and
 * suspicious patterns that could erode platform margins.
 */
class FraudPreventionService
{
    /**
     * Evaluate fraud risk before allowing a view session to start.
     *
     * @return array{score: float, flags: array, allowed: bool}
     */
    public function evaluate(Voter $voter, Request $request): array
    {
        $flags = [];
        $score = 0;

        // 1. Rate-limit: too many views today
        $viewsToday = $voter->viewSessions()->whereDate('created_at', today())->count();
        $maxPerDay  = config('dial4dough.fraud.max_views_per_voter_per_day', 50);
        if ($viewsToday >= $maxPerDay) {
            $flags[] = 'daily_limit_exceeded';
            $score += 50;
        }

        // 2. Device fingerprint check
        if (config('dial4dough.fraud.device_fingerprint_required', true)) {
            $fingerprint = $request->header('X-Device-Fingerprint') ?? $request->input('device_fingerprint');
            if (!$fingerprint) {
                $flags[] = 'missing_device_fingerprint';
                $score += 20;
            } elseif ($voter->device_fingerprint && $voter->device_fingerprint !== $fingerprint) {
                $flags[] = 'device_fingerprint_mismatch';
                $score += 30;
            }
        }

        // 3. IP-based anomalies: multiple voters from same IP
        $ip = $request->ip();
        $sameIpVoters = ViewSession::where('ip_address', $ip)
            ->whereDate('created_at', today())
            ->distinct('voter_id')
            ->count('voter_id');

        if ($sameIpVoters > config('dial4dough.fraud.suspicious_activity_threshold', 10)) {
            $flags[] = 'ip_shared_by_many_voters';
            $score += 25;
        }

        // 4. Rapid-fire views (< 5 seconds between completions)
        $lastView = $voter->viewSessions()
            ->where('status', ViewSessionStatus::Completed)
            ->orderByDesc('completed_at')
            ->first();

        if ($lastView && $lastView->completed_at && $lastView->completed_at->diffInSeconds(now()) < 5) {
            $flags[] = 'rapid_fire_views';
            $score += 40;
        }

        // 5. Already flagged
        if ($voter->flagged_for_fraud) {
            $flags[] = 'previously_flagged';
            $score += 60;
        }

        $allowed = $score < 50;

        if (!$allowed) {
            Log::warning('Fraud check failed', [
                'voter_id' => $voter->id,
                'score'    => $score,
                'flags'    => $flags,
                'ip'       => $ip,
            ]);
        }

        return [
            'score'   => min($score, 100),
            'flags'   => $flags,
            'allowed' => $allowed,
        ];
    }

    /**
     * Flag a voter for manual review.
     */
    public function flagVoter(Voter $voter, array $reasons = []): void
    {
        $voter->update([
            'flagged_for_fraud' => true,
            'trust_score' => max(0, $voter->trust_score - 25),
        ]);

        Log::info('Voter flagged for fraud', [
            'voter_id' => $voter->id,
            'reasons'  => $reasons,
        ]);
    }

    /**
     * Hold payouts for a voter during a fraud verification window.
     */
    public function holdPayouts(Voter $voter): void
    {
        ViewSession::where('voter_id', $voter->id)
            ->where('payment_status', ViewPaymentStatus::Approved)
            ->update(['payment_status' => ViewPaymentStatus::Held]);

        Log::info("Payouts held for voter {$voter->id}");
    }
}
