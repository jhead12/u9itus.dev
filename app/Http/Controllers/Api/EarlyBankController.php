<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ViewSession;
use App\Models\Voter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * EarlyBankController
 *
 * Server-to-server endpoints consumed by Early-bank.com. All requests are
 * authenticated by the `earlybank.api` middleware (shared bearer token).
 *
 * Routes are prefixed under /api/v1/earlybank.
 *
 * Contract notes:
 *   - U9itus is the source of truth for voter activity & earnings.
 *   - Early-bank is the source of truth for member identity & commission state.
 *   - These endpoints expose READ access to voter earnings and a WRITE endpoint
 *     for Early-bank to claim a referral linkage at registration time.
 */
class EarlyBankController extends Controller
{
    /**
     * POST /api/v1/earlybank/register-referral
     *
     * Called by Early-bank.com when a referred user completes u9itus signup.
     * Idempotent: re-linking with the same member_id is a no-op success.
     *
     * Body: { voter_uuid: string, earlybank_member_id: uuid }
     */
    public function registerReferral(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'voter_uuid'          => ['required', 'string', 'uuid'],
            'earlybank_member_id' => ['required', 'string', 'uuid'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error'  => 'validation_failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $voter = Voter::where('uuid', $request->string('voter_uuid'))->first();

        if (! $voter) {
            return response()->json(['error' => 'voter_not_found'], 404);
        }

        // Idempotency: if already linked to the same Early-bank member, succeed silently.
        if ($voter->earlybank_member_id === $request->string('earlybank_member_id')->value()) {
            return response()->json([
                'status'              => 'already_linked',
                'voter_uuid'          => $voter->uuid,
                'earlybank_member_id' => $voter->earlybank_member_id,
                'linked_at'           => optional($voter->earlybank_linked_at)->toIso8601String(),
            ]);
        }

        // Prevent silent reassignment to a different member.
        if ($voter->earlybank_member_id !== null) {
            return response()->json([
                'error'   => 'already_linked_to_other_member',
                'message' => 'Voter is already linked to a different Early-bank member.',
            ], 409);
        }

        $voter->forceFill([
            'earlybank_member_id' => $request->string('earlybank_member_id')->value(),
            'earlybank_linked_at' => now(),
        ])->save();

        return response()->json([
            'status'              => 'linked',
            'voter_uuid'          => $voter->uuid,
            'earlybank_member_id' => $voter->earlybank_member_id,
            'linked_at'           => $voter->earlybank_linked_at->toIso8601String(),
        ], 201);
    }

    /**
     * GET /api/v1/earlybank/voter/{voter:uuid}/earnings
     *
     * Returns aggregate viewing earnings for a single voter — used by Early-bank
     * to reconcile its commission ledger.
     */
    public function voterEarnings(Voter $voter): JsonResponse
    {
        if (! $voter->earlybank_member_id) {
            return response()->json(['error' => 'voter_not_linked_to_earlybank'], 403);
        }

        $completed = ViewSession::query()
            ->where('voter_id', $voter->id)
            ->whereNotNull('completed_at')
            ->selectRaw('COUNT(*) AS sessions, COALESCE(SUM(payout_amount), 0) AS total_payout')
            ->first();

        return response()->json([
            'voter_uuid'          => $voter->uuid,
            'earlybank_member_id' => $voter->earlybank_member_id,
            'sessions_completed'  => (int) ($completed->sessions ?? 0),
            'total_voter_payout'  => (float) ($completed->total_payout ?? 0),
            'wallet_balance'      => (float) $voter->wallet_balance,
            'total_earned'        => (float) $voter->total_earned,
        ]);
    }

    /**
     * GET /api/v1/earlybank/member/{member_id}/stats
     *
     * Aggregate stats for a single Early-bank member: how many u9itus voters
     * they've referred and how much those voters have collectively earned.
     */
    public function memberStats(string $memberId): JsonResponse
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $memberId)) {
            return response()->json(['error' => 'invalid_member_id'], 422);
        }

        $voterIds = Voter::query()
            ->where('earlybank_member_id', $memberId)
            ->pluck('id');

        if ($voterIds->isEmpty()) {
            return response()->json([
                'earlybank_member_id'        => $memberId,
                'referred_voters'            => 0,
                'sessions_completed'         => 0,
                'total_voter_payout'         => 0.0,
            ]);
        }

        $agg = ViewSession::query()
            ->whereIn('voter_id', $voterIds)
            ->whereNotNull('completed_at')
            ->selectRaw('COUNT(*) AS sessions, COALESCE(SUM(payout_amount), 0) AS total_payout')
            ->first();

        return response()->json([
            'earlybank_member_id' => $memberId,
            'referred_voters'     => $voterIds->count(),
            'sessions_completed'  => (int) ($agg->sessions ?? 0),
            'total_voter_payout'  => (float) ($agg->total_payout ?? 0),
        ]);
    }
}
