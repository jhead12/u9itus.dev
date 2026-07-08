<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Politician;
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

    /**
     * POST /api/v1/earlybank/member-enrolled
     *
     * Called by Early-bank.com when a U9itus user (voter or politician) joins
     * Early-bank as a paying member. Stores their own EB member UUID on the
     * appropriate model so U9itus can display their personal EB referral link.
     *
     * Body: {
     *   uuid:         string (the U9itus voter or politician uuid),
     *   member_uuid:  string (the Early-bank member UUID),
     *   u9itus_role:  'voter' | 'politician' | 'citizen'
     * }
     *
     * Idempotent: enrolling with the same member_uuid is a no-op success.
     */
    public function memberEnrolled(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'uuid'        => ['required', 'string', 'uuid'],
            'member_uuid' => ['required', 'string', 'uuid'],
            'u9itus_role' => ['required', 'string', 'in:voter,politician,citizen'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error'  => 'validation_failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $uuid       = $request->string('uuid')->value();
        $memberUuid = $request->string('member_uuid')->value();
        $role       = $request->string('u9itus_role')->value();

        // Route to the correct model based on role.
        if ($role === 'politician') {
            $model = Politician::where('uuid', $uuid)->first();
            $label = 'politician';
        } else {
            // Both 'voter' and 'citizen' roles live on the voters table.
            $model = Voter::where('uuid', $uuid)->first();
            $label = 'voter';
        }

        if (! $model) {
            return response()->json(['error' => "{$label}_not_found"], 404);
        }

        // Idempotency: already enrolled with the same member UUID — succeed silently.
        if ($model->earlybank_own_member_uuid === $memberUuid) {
            return response()->json([
                'status'      => 'already_enrolled',
                'uuid'        => $uuid,
                'member_uuid' => $memberUuid,
                'linked_at'   => optional($model->earlybank_own_linked_at)->toIso8601String(),
            ]);
        }

        // Prevent silent reassignment to a different Early-bank member account.
        if ($model->earlybank_own_member_uuid !== null) {
            return response()->json([
                'error'   => 'already_enrolled_other_member',
                'message' => 'This U9itus user is already linked to a different Early-bank member account.',
            ], 409);
        }

        $model->forceFill([
            'earlybank_own_member_uuid' => $memberUuid,
            'earlybank_own_linked_at'   => now(),
        ])->save();

        return response()->json([
            'status'      => 'enrolled',
            'uuid'        => $uuid,
            'member_uuid' => $memberUuid,
            'linked_at'   => $model->earlybank_own_linked_at->toIso8601String(),
        ], 201);
    }
}
