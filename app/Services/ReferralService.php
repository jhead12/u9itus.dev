<?php

namespace App\Services;

use App\Models\ReferralVisit;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Http\Request;

/**
 * Referral Service
 *
 * Resolves incoming referral codes from request/query/session/cookie sources
 * and records conversion attribution when a new user registers.
 */
class ReferralService
{
    /**
     * Resolve the best incoming referral code from all available sources.
     */
    public function resolveIncomingReferralCode(Request $request): ?string
    {
        $refCode = $request->input('referral_code')
            ?: $request->query('ref')
            ?: $request->query('referral_code')
            ?: $request->session()->get('referral.code')
            ?: $request->cookie('u9_referral_code');

        if (! is_string($refCode) || trim($refCode) === '') {
            return null;
        }

        return strtoupper(trim($refCode));
    }

    /**
     * Resolve a referral code into the matching referrer IDs.
     *
     * @return array{referred_by_voter_id: int|null, referred_by_politician_id: int|null}
     */
    public function resolveReferrerIds(?string $refCode): array
    {
        $referredByVoterId      = null;
        $referredByPoliticianId = null;

        if (! is_string($refCode) || trim($refCode) === '') {
            return compact('referredByVoterId', 'referredByPoliticianId');
        }

        $voterReferrer = Voter::where('referral_code', $refCode)->first();
        if ($voterReferrer) {
            $referredByVoterId = $voterReferrer->id;
        } else {
            $politicianReferrer = \App\Models\Politician::where('referral_code', $refCode)->first();
            $referredByPoliticianId = $politicianReferrer?->id;
        }

        return compact('referredByVoterId', 'referredByPoliticianId');
    }

    /**
     * Mark the most recent unconverted referral visit for this session as
     * converted by the given user.
     */
    public function markReferralConversion(Request $request, ?string $refCode, User $user): void
    {
        if (! is_string($refCode) || trim($refCode) === '') {
            return;
        }

        $sessionId = $request->session()->getId();

        if (! is_string($sessionId) || $sessionId === '') {
            return;
        }

        $visit = ReferralVisit::where('referral_code', strtoupper(trim($refCode)))
            ->where('session_id', $sessionId)
            ->latest('id')
            ->first();

        if (! $visit) {
            $visit = ReferralVisit::where('referral_code', strtoupper(trim($refCode)))
                ->whereNull('converted_user_id')
                ->latest('id')
                ->first();
        }

        if (! $visit || $visit->converted_user_id) {
            return;
        }

        $visit->update([
            'converted_user_id'   => $user->id,
            'converted_user_type' => $user->user_type,
            'converted_at'        => now(),
        ]);
    }
}
