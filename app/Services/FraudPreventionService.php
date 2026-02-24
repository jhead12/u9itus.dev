<?php

namespace App\Services;

use App\Enums\ViewPaymentStatus;
use App\Enums\ViewSessionStatus;
use App\Events\FraudFlagRaised;
use App\Models\FraudSignal;
use App\Models\ViewSession;
use App\Models\Voter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Fraud detection & prevention for view sessions — Phase 8.
 *
 * Integrates five signal categories:
 *  1. Rate limiting          (daily view cap)
 *  2. Device fingerprinting  (missing / mismatch / bot UA)
 *  3. IP anomalies           (shared IP across voters)
 *  4. IP reputation          (VPN / proxy / Tor / datacenter — via IpReputationService)
 *  5. Behavioural            (rapid-fire completions, previously flagged)
 *
 * Each signal contributes a weighted score.  A total ≥ 50 blocks the session.
 * Signals above the auto-flag threshold (80) automatically flag the voter
 * and broadcast a FraudFlagRaised event to the admin monitor channel.
 *
 * All signals are persisted to fraud_signals for audit and ML feature ingestion.
 */
class FraudPreventionService
{
    public function __construct(
        private readonly IpReputationService      $ipReputation,
        private readonly DeviceFingerprintService $deviceFingerprint,
    ) {}

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Evaluate fraud risk before allowing a view session to start.
     *
     * @param  string|null  $sessionUuid  ViewSession UUID for signal linkage (optional)
     * @return array{score: float, flags: array<string>, allowed: bool}
     */
    public function evaluate(Voter $voter, Request $request, ?string $sessionUuid = null): array
    {
        $flags = [];
        $score = 0;
        $ip    = $request->ip() ?? '127.0.0.1';

        // ── 1. Rate-limit: too many views today ──────────────────────────────
        $viewsToday = $voter->viewSessions()->whereDate('created_at', today())->count();
        $maxPerDay  = config('u9itus.fraud.max_views_per_voter_per_day', 50);
        if ($viewsToday >= $maxPerDay) {
            $flags[] = 'daily_limit_exceeded';
            $score  += 50;
            $this->record($voter, 'daily_limit_exceeded', 50, $ip, null, null, $sessionUuid, [
                'views_today' => $viewsToday,
                'limit'       => $maxPerDay,
            ]);
        }

        // ── 2. Device fingerprint ────────────────────────────────────────────
        if (config('u9itus.fraud.device_fingerprint_required', true)) {
            $fp        = $this->deviceFingerprint->generate($request);
            $cmpResult = $this->deviceFingerprint->compare($fp, $voter);

            if ($cmpResult === 'new') {
                // First session — store fingerprint, no penalty
                $this->deviceFingerprint->storeIfNew($fp, $voter);
            } elseif ($cmpResult === 'mismatch') {
                $flags[] = 'device_fingerprint_mismatch';
                $score  += 30;
                $this->record($voter, 'device_fingerprint_mismatch', 30, $ip, $fp, null, $sessionUuid);
            }

            // ── 2b. Bot user-agent check ─────────────────────────────────────
            $uaCheck = $this->deviceFingerprint->analyseUserAgent($request->userAgent() ?? '');
            if ($uaCheck['is_bot']) {
                $flags[] = 'bot_user_agent';
                $score  += 30;
                $this->record($voter, 'bot_user_agent', 30, $ip, $fp, null, $sessionUuid, [
                    'reason'     => $uaCheck['reason'],
                    'user_agent' => substr($request->userAgent() ?? '', 0, 200),
                ]);
            }
        }

        // ── 3. IP anomaly: multiple distinct voters from same IP ─────────────
        $sameIpVoters = ViewSession::where('ip_address', $ip)
            ->whereDate('created_at', today())
            ->distinct('voter_id')
            ->count('voter_id');

        $ipThreshold = config('u9itus.fraud.suspicious_activity_threshold', 10);
        if ($sameIpVoters > $ipThreshold) {
            $flags[] = 'ip_shared_by_many_voters';
            $score  += 25;
            $this->record($voter, 'ip_shared_by_many_voters', 25, $ip, null, null, $sessionUuid, [
                'same_ip_voter_count' => $sameIpVoters,
            ]);
        }

        // ── 4. IP Reputation: VPN / proxy / Tor / datacenter ────────────────
        if (config('u9itus.fraud.ip_reputation_enabled', true) && $ip !== '127.0.0.1') {
            $rep = $this->ipReputation->assess($ip);

            if ($rep['is_tor']) {
                $flags[] = 'tor_exit_node';
                $score  += 50;
                $this->record($voter, 'tor_exit_node', 50, $ip, null, $rep['provider'] ?? null, $sessionUuid);
            }

            if ($rep['is_vpn'] && ! $rep['is_tor']) {
                $flags[] = 'vpn_detected';
                $score  += 35;
                $this->record($voter, 'vpn_detected', 35, $ip, null, $rep['provider'] ?? null, $sessionUuid, [
                    'is_datacenter' => $rep['is_datacenter'],
                ]);
            } elseif ($rep['is_datacenter'] && ! $rep['is_vpn'] && ! $rep['is_tor']) {
                $flags[] = 'datacenter_ip';
                $score  += 25;
                $this->record($voter, 'datacenter_ip', 25, $ip, null, $rep['provider'] ?? null, $sessionUuid);
            }
        }

        // ── 5. Behavioural: rapid-fire completions ───────────────────────────
        $lastView = $voter->viewSessions()
            ->where('status', ViewSessionStatus::Completed)
            ->orderByDesc('completed_at')
            ->first();

        if ($lastView && $lastView->completed_at && $lastView->completed_at->diffInSeconds(now()) < 5) {
            $flags[] = 'rapid_fire_views';
            $score  += 40;
            $this->record($voter, 'rapid_fire_views', 40, $ip, null, null, $sessionUuid, [
                'last_completed_at' => $lastView->completed_at->toIso8601String(),
                'seconds_ago'       => $lastView->completed_at->diffInSeconds(now()),
            ]);
        }

        // ── 6. Previously flagged ────────────────────────────────────────────
        if ($voter->flagged_for_fraud) {
            $flags[] = 'previously_flagged';
            $score  += 60;
        }

        $score   = min((int) $score, 100);
        $allowed = $score < 50;

        if (! $allowed) {
            Log::warning('FraudPreventionService: session blocked', [
                'voter_id' => $voter->id,
                'score'    => $score,
                'flags'    => $flags,
                'ip'       => $ip,
            ]);

            // Auto-flag + broadcast when score crosses the hard threshold
            if ($score >= config('u9itus.fraud.auto_flag_threshold', 80) && ! $voter->flagged_for_fraud) {
                $this->flagVoter($voter, $flags);
                $primaryReason = implode(', ', $flags);
                event(new FraudFlagRaised($voter, $score, $primaryReason));
            }
        }

        return [
            'score'   => $score,
            'flags'   => $flags,
            'allowed' => $allowed,
        ];
    }

    /**
     * Flag a voter for manual review and reduce their trust score.
     */
    public function flagVoter(Voter $voter, array $reasons = []): void
    {
        $voter->update([
            'flagged_for_fraud' => true,
            'trust_score'       => max(0, $voter->trust_score - 25),
        ]);

        Log::info('FraudPreventionService: voter flagged', [
            'voter_id' => $voter->id,
            'reasons'  => $reasons,
        ]);
    }

    /**
     * Adjust a voter's trust score by a signed delta.
     *
     * Positive delta = reward good behaviour (e.g. after a clean audit).
     * Negative delta = penalise suspicious behaviour.
     */
    public function updateTrustScore(Voter $voter, int $delta): void
    {
        $new = max(0, min(100, (int) $voter->trust_score + $delta));
        $voter->update(['trust_score' => $new]);
    }

    /**
     * Hold payouts for a voter during a fraud verification window.
     * Sets all Approved sessions to Held.
     */
    public function holdPayouts(Voter $voter): void
    {
        ViewSession::where('voter_id', $voter->id)
            ->where('payment_status', ViewPaymentStatus::Approved)
            ->update(['payment_status' => ViewPaymentStatus::Held]);

        Log::info("FraudPreventionService: payouts held for voter {$voter->id}");
    }

    /**
     * Release held payouts back to Approved after a fraud flag is cleared.
     */
    public function releasePayouts(Voter $voter): void
    {
        ViewSession::where('voter_id', $voter->id)
            ->where('payment_status', ViewPaymentStatus::Held)
            ->update(['payment_status' => ViewPaymentStatus::Approved]);

        Log::info("FraudPreventionService: payouts released for voter {$voter->id}");
    }

    /**
     * Clear the fraud flag on a voter and restore trust score.
     * Optionally releases held payouts at the same time.
     */
    public function clearFlag(Voter $voter, bool $releaseHeldPayouts = false): void
    {
        $voter->update([
            'flagged_for_fraud' => false,
            'trust_score'       => min(100, $voter->trust_score + 10),
        ]);

        if ($releaseHeldPayouts) {
            $this->releasePayouts($voter);
        }

        Log::info("FraudPreventionService: flag cleared for voter {$voter->id}");
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    /**
     * Persist a single fraud signal to the audit table.
     */
    private function record(
        Voter    $voter,
        string   $signalType,
        int      $scoreImpact,
        string   $ip,
        ?string  $fingerprint  = null,
        ?string  $provider     = null,
        ?string  $sessionUuid  = null,
        array    $metadata     = [],
    ): void {
        try {
            FraudSignal::create([
                'voter_id'           => $voter->id,
                'view_session_uuid'  => $sessionUuid,
                'signal_type'        => $signalType,
                'score_impact'       => $scoreImpact,
                'ip_address'         => $ip,
                'device_fingerprint' => $fingerprint,
                'provider'           => $provider,
                'metadata'           => empty($metadata) ? null : $metadata,
            ]);
        } catch (\Throwable $e) {
            // Never let signal recording break the main request flow.
            Log::error('FraudPreventionService: failed to record signal', [
                'voter_id'    => $voter->id,
                'signal_type' => $signalType,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
