<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

/**
 * Registration Security Service
 *
 * Pre-registration security checks to prevent:
 * - Multiple accounts from same IP with KYC-approved status
 * - Rate-limit abuse (too many registrations from one IP)
 * - Registrations from blocked/flagged IPs
 * - Duplicate phone numbers
 *
 * All checks are logged to registration_attempts for auditing.
 */
class RegistrationSecurityService
{
    public function __construct(
        private readonly RegistrationContentGuard $contentGuard,
    ) {}

    /**
     * Evaluate registration security — throws exception if blocked.
     *
     * @param  Request  $request
     * @param  string|null  $email
     * @param  string|null  $honeypot  Value of the hidden honeypot field (should always be empty for humans).
     * @return array{flagged: bool, fraud_score: int, fraud_reasons: array<string>}
     * @throws \Illuminate\Validation\ValidationException
     */
    public function checkOrFail(Request $request, ?string $email = null, ?string $honeypot = null): array
    {
        $ip = $request->ip() ?? '127.0.0.1';
        $email = $email ?? $request->input('email', '');
        $userAgent = substr($request->userAgent() ?? '', 0, 500);

        // ── 1. IP on permanent/temporary blocklist? ──────────────────────────
        if ($this->isIpBlocked($ip)) {
            $this->logAttempt($ip, $email, $userAgent, 'blocked', 'ip_blocked');
            throw \Illuminate\Validation\ValidationException::withMessages([
                'ip' => 'This IP address is blocked from registration.',
            ]);
        }

        // ── 2. Rate limit: too many registrations from this IP today ─────────
        $todayCount = $this->getRegistrationCountToday($ip);
        $maxPerDay = config('u9itus.security.max_registrations_per_ip_per_day', 10);
        if ($todayCount >= $maxPerDay) {
            $this->logAttempt($ip, $email, $userAgent, 'blocked', 'rate_limit_exceeded', [
                'registrations_today' => $todayCount,
                'limit'               => $maxPerDay,
            ]);
            throw \Illuminate\Validation\ValidationException::withMessages([
                'ip' => "Too many registrations from this IP. Please try again tomorrow.",
            ]);
        }

        // ── 3. KYC duplicate detection: does this IP already have an approved KYC account? ────
        $approvedKycUser = $this->getApprovedKycUserByIp($ip);
        if ($approvedKycUser) {
            $this->logAttempt($ip, $email, $userAgent, 'blocked', 'kyc_duplicate_detected', [
                'existing_user_id' => $approvedKycUser->id,
                'existing_email'   => $approvedKycUser->email,
            ]);
            throw \Illuminate\Validation\ValidationException::withMessages([
                'ip' => 'An account with verified identity (KYC) already exists on this IP. One verified account per IP is allowed.',
            ]);
        }

        // ── 4. Phone duplicate check (if phone provided) ──────────────────────
        $phone = $request->input('phone');
        if ($phone && $this->isPhoneDuplicate($phone)) {
            $this->logAttempt($ip, $email, $userAgent, 'blocked', 'phone_already_registered', [
                'phone' => substr($phone, -4), // Last 4 digits only for PII safety
            ]);
            throw \Illuminate\Validation\ValidationException::withMessages([
                'phone' => 'This phone number is already registered.',
            ]);
        }

        // ── 5. Content heuristics: gibberish name / disposable or dot-farmed email ──
        $content = $this->contentGuard->evaluate(
            (string) $request->input('first_name', ''),
            (string) $request->input('last_name', ''),
            $email,
            $honeypot
        );

        $hardBlockThreshold = config('u9itus.security.content_fraud_hard_block_threshold', 80);
        $flagThreshold = config('u9itus.security.content_fraud_flag_threshold', 40);

        if ($content['hard_block'] || $content['score'] >= $hardBlockThreshold) {
            $this->logAttempt($ip, $email, $userAgent, 'blocked', 'content_fraud_high', [
                'score' => $content['score'],
                'reasons' => $content['reasons'],
            ]);
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => 'We were unable to verify this registration. Please use a real name and email address.',
            ]);
        }

        $flagged = $content['score'] >= $flagThreshold;

        // ── 6. Passed all checks — log as allowed ────────────────────────────
        $this->logAttempt($ip, $email, $userAgent, 'allowed', $flagged ? 'registration_flagged' : 'registration_allowed', [
            'score' => $content['score'],
            'reasons' => $content['reasons'],
        ]);

        return [
            'flagged' => $flagged,
            'fraud_score' => $content['score'],
            'fraud_reasons' => $content['reasons'],
        ];
    }

    /**
     * Check if an IP is on the blocklist (permanent or temporary).
     */
    private function isIpBlocked(string $ip): bool
    {
        return DB::table('ip_registration_blocks')
            ->where('ip_address', $ip)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    /**
     * Count registrations from IP today (allowed only).
     */
    private function getRegistrationCountToday(string $ip): int
    {
        return DB::table('registration_attempts')
            ->where('ip_address', $ip)
            ->where('outcome', 'allowed')
            ->whereDate('created_at', today())
            ->count();
    }

    /**
     * Get an approved KYC user registered from this IP (if exists).
     * Returns the first one found.
     */
    private function getApprovedKycUserByIp(string $ip): ?User
    {
        return User::where('registration_ip', $ip)
            ->where('kyc_status', 'approved')
            ->orWhere(function ($q) use ($ip) {
                $q->where('registration_ip', $ip)
                  ->whereNotNull('idme_verified_at');
            })
            ->first();
    }

    /**
     * Check if phone is already registered.
     */
    private function isPhoneDuplicate(string $phone): bool
    {
        return User::where('phone', $phone)
            ->whereNotNull('phone')
            ->exists();
    }

    /**
     * Log a registration attempt for audit and analytics.
     */
    private function logAttempt(
        string $ip,
        string $email,
        string $userAgent,
        string $outcome,
        string $reason,
        ?array $metadata = null
    ): void {
        DB::table('registration_attempts')->insert([
            'ip_address'  => $ip,
            'email'       => $email ?: null,
            'user_agent'  => $userAgent,
            'outcome'     => $outcome,
            'reason'      => $reason,
            'metadata'    => $metadata ? json_encode($metadata) : null,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Log to application logs if blocked or flagged
        if ($outcome !== 'allowed') {
            Log::warning("Registration attempt: {$outcome} ({$reason})", [
                'ip'        => $ip,
                'email'     => $email,
                'reason'    => $reason,
                'metadata'  => $metadata,
            ]);
        }
    }

    /**
     * Admin API: Block an IP (temporarily or permanently).
     */
    public function blockIp(string $ip, string $reason, ?string $expiresAt = null, ?int $blockedByUserId = null): void
    {
        DB::table('ip_registration_blocks')->updateOrInsert(
            ['ip_address' => $ip],
            [
                'reason'              => $reason,
                'expires_at'          => $expiresAt ? Carbon::parse($expiresAt) : null,
                'blocked_by_user_id'  => $blockedByUserId,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]
        );

        Log::info("IP blocked: {$ip} ({$reason})", ['expires_at' => $expiresAt]);
    }

    /**
     * Admin API: Unblock an IP.
     */
    public function unblockIp(string $ip): void
    {
        DB::table('ip_registration_blocks')->where('ip_address', $ip)->delete();
        Log::info("IP unblocked: {$ip}");
    }

    /**
     * Admin API: Get all current active blocks.
     */
    public function getActiveBlocks(): \Illuminate\Pagination\Paginator
    {
        return DB::table('ip_registration_blocks')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('created_at')
            ->paginate(50);
    }

    /**
     * Admin API: Get registration attempts by IP (with pagination).
     */
    public function getAttemptsByIp(string $ip, int $limit = 100): \Illuminate\Pagination\Paginator
    {
        return DB::table('registration_attempts')
            ->where('ip_address', $ip)
            ->orderByDesc('created_at')
            ->paginate($limit);
    }
}
