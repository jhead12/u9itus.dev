<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Citizen;
use App\Models\Politician;
use App\Models\ViewSession;
use App\Models\Voter;
use App\Services\EarlyBankInboundService;
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
     * POST /api/v1/earlybank/webhook
     *
     * Receives signed inbound events from Early-bank.com (commissions, bonuses,
     * and member status updates). Requests are authenticated by the shared
     * `earlybank.api` bearer middleware and then by HMAC signature verification.
     *
     * Always returns 200 for verified requests so EB's retry queue does not
     * back up; unverified requests return 401. A JSON body explains whether the
     * event was processed.
     */
    public function webhook(Request $request, EarlyBankInboundService $service): JsonResponse
    {
        $result = $service->handleWebhook($request);

        if (! $result['verified']) {
            return response()->json([
                'status'  => 'unverified',
                'error'   => $result['error'],
                'event_id' => $result['event_id'],
            ], 401);
        }

        return response()->json([
            'status'    => $result['processed'] ? 'processed' : 'accepted',
            'event_id'  => $result['event_id'],
            'error'     => $result['error'],
        ]);
    }

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
            'voter_uuid'              => $voter->uuid,
            'earlybank_member_id'     => $voter->earlybank_member_id,
            'sessions_completed'      => (int) ($completed->sessions ?? 0),
            'total_voter_payout'      => (float) ($completed->total_payout ?? 0),
            'wallet_balance'          => (float) $voter->wallet_balance,
            'total_earned'            => (float) $voter->total_earned,
            'earlybank_earnings_total' => (float) $voter->earlybank_earnings_total,
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
     * GET /api/v1/earlybank/voter-by-email?email=<email>
     *
     * Resolves a voter's UUID from their email address. Used by the Early-bank
     * DashboardController to link users who registered on U9itus *before*
     * creating their Early-bank account — those users have `user_type = 'voter'`
     * on the shared table but never went through the webhook registration flow,
     * so Early-bank needs to look them up by email to get their UUID before
     * calling `member-enrolled`.
     *
     * Returns the voter's UUID and current EB membership status.
     */
    public function voterByEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:254'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error'  => 'validation_failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $voter = Voter::where('email', strtolower(trim($request->string('email')->value())))->first();

        if (! $voter) {
            return response()->json(['error' => 'voter_not_found'], 404);
        }

        return response()->json([
            'voter_uuid'               => $voter->uuid,
            'earlybank_member_id'      => $voter->earlybank_member_id,       // who referred them
            'earlybank_own_member_uuid' => $voter->earlybank_own_member_uuid, // their own EB membership (null if not yet linked)
            'earlybank_own_linked_at'  => optional($voter->earlybank_own_linked_at)->toIso8601String(),
            'earlybank_payouts_enabled'              => (bool) $voter->earlybank_payouts_enabled,
            'earlybank_stripe_connect_onboarding_complete' => (bool) $voter->earlybank_stripe_connect_onboarding_complete,
        ]);
    }

    /**
     * POST /api/v1/earlybank/member-enrolled
     *
     * Called by Early-bank.com when a U9itus user (voter, politician, or
     * citizen) joins Early-bank as a paying member. Stores their own EB member
     * UUID on the appropriate model so U9itus can display their personal EB
     * referral link, plus the member's Stripe Connect / payouts state so U9itus
     * can gate that surface on onboarding completion.
     *
     * Body: {
     *   uuid:                                  string (U9itus voter|politician|citizen uuid),
     *   member_uuid:                            string (Early-bank member UUID),
     *   u9itus_role:                            'voter' | 'politician' | 'citizen',
     *   payouts_enabled:                        bool (optional),
     *   stripe_connect_account_id:              string|null (optional),
     *   stripe_connect_onboarding_complete:      bool (optional)
     * }
     *
     * Idempotent: re-enrolling with the same member_uuid is a 200 success, but
     * still syncs the Stripe Connect / payouts fields if provided (onboarding
     * can complete later and Early-bank re-posts). The member UUID itself is
     * never silently reassigned — a different member_uuid returns 409.
     */
    public function memberEnrolled(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'uuid'                                    => ['required', 'string', 'uuid'],
            'member_uuid'                             => ['required', 'string', 'uuid'],
            'u9itus_role'                             => ['required', 'string', 'in:voter,politician,citizen'],
            'payouts_enabled'                         => ['sometimes', 'boolean'],
            'stripe_connect_account_id'              => ['sometimes', 'nullable', 'string', 'max:255'],
            'stripe_connect_onboarding_complete'      => ['sometimes', 'boolean'],
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

        // Route to the correct model based on role. Citizens live on their own
        // `citizens` table (with its own uuid), not the voters table.
        if ($role === 'politician') {
            $model = Politician::where('uuid', $uuid)->first();
            $label = 'politician';
        } elseif ($role === 'citizen') {
            $model = Citizen::where('uuid', $uuid)->first();
            $label = 'citizen';
        } else {
            $model = Voter::where('uuid', $uuid)->first();
            $label = 'voter';
        }

        if (! $model) {
            return response()->json(['error' => "{$label}_not_found"], 404);
        }

        $stripeFields = $this->extractStripeState($request);

        // Idempotency: already enrolled with the same member UUID. Still sync the
        // Stripe Connect / payouts fields if provided (Early-bank re-posts when
        // onboarding completes), but never touch the member UUID itself.
        if ($model->earlybank_own_member_uuid === $memberUuid) {
            if (! empty($stripeFields)) {
                $model->forceFill($stripeFields)->save();
            }

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

        $model->forceFill(array_merge([
            'earlybank_own_member_uuid' => $memberUuid,
            'earlybank_own_linked_at'   => now(),
        ], $stripeFields))->save();

        return response()->json([
            'status'      => 'enrolled',
            'uuid'        => $uuid,
            'member_uuid' => $memberUuid,
            'linked_at'   => $model->earlybank_own_linked_at->toIso8601String(),
        ], 201);
    }

    /**
     * Pull the optional Stripe Connect / payouts fields off the request,
     * returning only the ones actually present so forceFill does not blank out
     * columns Early-bank did not send.
     *
     * @return array<string, mixed>
     */
    private function extractStripeState(Request $request): array
    {
        $fields = [];

        if ($request->has('payouts_enabled')) {
            $fields['earlybank_payouts_enabled'] = (bool) $request->boolean('payouts_enabled');
        }

        if ($request->has('stripe_connect_account_id')) {
            $id = $request->input('stripe_connect_account_id');
            $fields['earlybank_stripe_connect_account_id'] = is_string($id) ? trim($id) : null;
            if ($fields['earlybank_stripe_connect_account_id'] === '') {
                $fields['earlybank_stripe_connect_account_id'] = null;
            }
        }

        if ($request->has('stripe_connect_onboarding_complete')) {
            $fields['earlybank_stripe_connect_onboarding_complete'] = (bool) $request->boolean('stripe_connect_onboarding_complete');
        }

        return $fields;
    }
}
