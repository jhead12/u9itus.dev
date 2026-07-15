<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Mail\AccountUnsuspendedMail;
use App\Models\DeletedAccount;
use App\Models\EarlyBankWebhookLog;
use App\Models\PayoutAttempt;
use App\Models\User;
use App\Notifications\SystemAnnouncementNotification;
use App\Services\UserDeletionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Admin user-account management.
 *
 * Split out of AdminController. Lists/searches users, applies bulk
 * suspend/unsuspend/KYC actions, shows per-user detail (incl. referral,
 * payout-attempt, and Early-Bank webhook history for voters), and handles
 * soft-delete archive/restore via UserDeletionService plus suspend/unsuspend
 * with best-effort reactivation notifications.
 */
class AdminUserController extends Controller
{
    /**
     * List all users.
     */
    public function users(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $role = (string) $request->query('role', '');
        $kyc = (string) $request->query('kyc', '');
        $accountStatus = (string) $request->query('account_status', '');
        $authenticUserVerifier = (string) $request->query('authentic_user_verifier', '');

        $allowedRoles = ['admin', 'politician', 'voter'];
        $allowedKycStatuses = ['approved', 'pending', 'rejected'];
        $allowedAccountStatuses = ['active', 'unverified', 'suspended'];
        $allowedAuthenticUserVerifierStatuses = ['pending', 'completed'];

        $usersQuery = User::query()->with(['politician', 'voter']);

        if ($search !== '') {
            $likeSearch = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';

            $usersQuery->where(function (Builder $query) use ($search, $likeSearch) {
                $query->where('name', 'like', $likeSearch)
                    ->orWhere('email', 'like', $likeSearch)
                    ->orWhere('phone', 'like', $likeSearch)
                    ->orWhereHas('politician', function (Builder $politicianQuery) use ($likeSearch) {
                        $politicianQuery->where('full_name', 'like', $likeSearch)
                            ->orWhere('political_office', 'like', $likeSearch)
                            ->orWhere('city', 'like', $likeSearch)
                            ->orWhere('state', 'like', $likeSearch);
                    })
                    ->orWhereHas('voter', function (Builder $voterQuery) use ($likeSearch) {
                        $voterQuery->where('email', 'like', $likeSearch)
                            ->orWhere('city', 'like', $likeSearch)
                            ->orWhere('state', 'like', $likeSearch);
                    });

                if (ctype_digit($search)) {
                    $query->orWhere('id', (int) $search);
                }
            });
        }

        if (in_array($role, $allowedRoles, true)) {
            $usersQuery->where('user_type', $role);
        }

        if (in_array($kyc, $allowedKycStatuses, true)) {
            $usersQuery->where('kyc_status', $kyc);
        }

        if (in_array($accountStatus, $allowedAccountStatuses, true)) {
            $usersQuery->where(function (Builder $query) use ($accountStatus) {
                if ($accountStatus === 'suspended') {
                    $query->whereNotNull('suspended_at');
                    return;
                }

                if ($accountStatus === 'active') {
                    $query->whereNull('suspended_at')
                        ->whereNotNull('email_verified_at');
                    return;
                }

                $query->whereNull('suspended_at')
                    ->whereNull('email_verified_at');
            });
        }

        if (in_array($authenticUserVerifier, $allowedAuthenticUserVerifierStatuses, true)) {
            // Authentic User Verifier applies to legacy voter accounts migrating to Stripe Connect.
            $usersQuery->where('user_type', 'voter')
                ->whereHas('voter', function (Builder $voterQuery) use ($authenticUserVerifier) {
                    $voterQuery->whereHas('user', function (Builder $legacyUser) {
                        $legacyUser->where('user_type', 'voter')
                            ->where(function (Builder $legacy) {
                                $legacy->whereNotNull('kyc_document_path')
                                    ->orWhereNotNull('idme_verified_at')
                                    ->orWhereIn('kyc_status', ['pending', 'approved', 'rejected']);
                            });
                    });

                    if ($authenticUserVerifier === 'pending') {
                        $voterQuery->where(function (Builder $stripe) {
                            $stripe->whereNull('stripe_account_id')
                                ->orWhere('stripe_account_id', '')
                                ->orWhereNull('stripe_account_status')
                                ->orWhere('stripe_account_status', '!=', 'active');
                        });
                        return;
                    }

                    $voterQuery->whereNotNull('stripe_account_id')
                        ->where('stripe_account_id', '!=', '')
                        ->where('stripe_account_status', 'active');
                });
        }

        $users = $usersQuery
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('standalone.admin.users', compact('users'));
    }

    /**
     * Apply a bulk action to selected users from the users index page.
     */
    public function bulkUserAction(Request $request)
    {
        $validated = $request->validate([
            'action' => ['required', 'in:suspend,unsuspend,kyc_approve,kyc_reject'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $action = (string) $validated['action'];
        $userIds = collect($validated['user_ids'])
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values();

        $users = User::query()
            ->with(['politician', 'voter'])
            ->whereIn('id', $userIds)
            ->get();

        if ($users->isEmpty()) {
            return back()->withErrors(['error' => 'No users were selected.']);
        }

        $updated = 0;
        $skippedAdmins = 0;
        $skippedVoters = 0;
        $reviewedAt = now();

        foreach ($users as $user) {
            if ($user->user_type === 'admin' && in_array($action, ['suspend', 'kyc_approve', 'kyc_reject'], true)) {
                $skippedAdmins++;
                continue;
            }

            // Voters use Stripe for identity verification; skip manual KYC bulk actions.
            if ($user->user_type === 'voter' && in_array($action, ['kyc_approve', 'kyc_reject'], true)) {
                $skippedVoters++;
                continue;
            }

            if ($action === 'suspend') {
                if ($user->isSuspended()) {
                    continue;
                }

                $user->update([
                    'suspended_at' => now(),
                    'suspension_reason' => 'Suspended by administrator (bulk action).',
                ]);

                if ($user->voter) {
                    $user->voter->update(['is_active' => false]);
                }
                if ($user->politician) {
                    $user->politician->update(['is_active' => false]);
                }

                $updated++;
                continue;
            }

            if ($action === 'unsuspend') {
                if (! $user->isSuspended()) {
                    continue;
                }

                $user->update([
                    'suspended_at' => null,
                    'suspension_reason' => null,
                ]);

                if ($user->voter) {
                    $user->voter->update(['is_active' => true]);
                }
                if ($user->politician) {
                    $user->politician->update(['is_active' => true]);
                }

                $updated++;
                continue;
            }

            if ($action === 'kyc_approve') {
                $user->update([
                    'kyc_status' => 'approved',
                    'kyc_reviewed_at' => $reviewedAt,
                    'kyc_reviewer_id' => auth()->id(),
                    'kyc_rejection_reason' => null,
                ]);

                $updated++;
                continue;
            }

            $user->update([
                'kyc_status' => 'rejected',
                'kyc_reviewed_at' => $reviewedAt,
                'kyc_reviewer_id' => auth()->id(),
                'kyc_rejection_reason' => 'Rejected by administrator (bulk action).',
            ]);

            $updated++;
        }

        $labels = [
            'suspend' => 'suspended',
            'unsuspend' => 'unsuspended',
            'kyc_approve' => 'KYC approved for',
            'kyc_reject' => 'KYC rejected for',
        ];

        if ($updated === 0) {
            $noneAppliedMessage = 'No selected users were eligible for that action.';

            if ($skippedAdmins > 0) {
                $noneAppliedMessage .= ' Admin accounts were skipped.';
            }
            if ($skippedVoters > 0) {
                $noneAppliedMessage .= ' Voter accounts were skipped (use Stripe for voter verification).';
            }

            return back()->withErrors(['error' => $noneAppliedMessage]);
        }

        $message = $updated . ' user(s) ' . $labels[$action] . '.';

        if ($skippedAdmins > 0) {
            $message .= ' ' . $skippedAdmins . ' admin account(s) skipped.';
        }
        if ($skippedVoters > 0) {
            $message .= ' ' . $skippedVoters . ' voter account(s) skipped (Stripe-verified).';
        }

        return back()->with('success', $message);
    }

    /**
     * Show user details.
     */
    public function showUser($userId)
    {
        $user = User::with([
            'politician',
            'voter.referrer.user:id,name,email',
            'voter.politicianReferrer:id,full_name',
            'voter.fraudSignals' => fn ($q) => $q->latest()->take(5),
            'voter.viewSessions' => fn ($q) => $q->latest()->take(10),
        ])->findOrFail($userId);

        $voterStats    = null;
        $ebWebhookLogs = collect();

        if ($user->voter) {
            $voterStats = [
                'referral_count'    => $user->voter->referrals()->count(),
                'referral_earnings' => (float) $user->voter->referralEarnings()->sum('commission_amount'),
                'payout_attempts'   => PayoutAttempt::where('voter_id', $user->voter->id)
                    ->selectRaw("status, COUNT(*) as total")
                    ->groupBy('status')
                    ->pluck('total', 'status'),
            ];

            // EB webhook history for this voter (as referred voter OR as EB member)
            $voterUuid = $user->voter->uuid;
            $ownMemberUuid = $user->voter->earlybank_own_member_uuid;

            $ebQuery = EarlyBankWebhookLog::query()
                ->where(function ($q) use ($voterUuid, $ownMemberUuid) {
                    $q->where('voter_uuid', $voterUuid);
                    if ($ownMemberUuid) {
                        // Also show events where THIS voter's EB membership was credited
                        $q->orWhere('earlybank_member_id', $ownMemberUuid);
                    }
                });

            $ebWebhookLogs = $ebQuery->latest()->take(25)->get();
        }

        return view('standalone.admin.user-details', compact('user', 'voterStats', 'ebWebhookLogs'));
    }

    public function deleteUser(Request $request, $userId, UserDeletionService $service)
    {
        $user = User::findOrFail($userId);

        if ($user->user_type === 'admin') {
            return back()->withErrors(['error' => 'Admin accounts cannot be deleted.']);
        }

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['error' => 'You cannot delete your own account.']);
        }

        $validated = $request->validate([
            'deletion_reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->archiveAndDelete(
                $user,
                $request->user(),
                $validated['deletion_reason'] ?? null,
                $request->ip()
            );
        } catch (\Throwable $e) {
            Log::error('AdminController@deleteUser failed', ['error' => $e->getMessage(), 'user_id' => $userId]);
            return back()->withErrors(['error' => 'Failed to delete account: ' . $e->getMessage()]);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'Account for ' . $user->email . ' has been deleted and archived.');
    }

    public function deletedAccounts(Request $request)
    {
        $query = DeletedAccount::latest('deleted_at');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $records = $query->paginate(30)->withQueryString();

        return view('standalone.admin.deleted-accounts', compact('records'));
    }

    public function restoreDeletedAccount(Request $request, $recordId, UserDeletionService $service)
    {
        $record = DeletedAccount::findOrFail($recordId);

        try {
            $newUser = $service->restore($record, $request->user(), $request->ip());
        } catch (\Throwable $e) {
            Log::error('AdminController@restoreDeletedAccount failed', ['error' => $e->getMessage(), 'record_id' => $recordId]);
            return back()->withErrors(['error' => 'Restore failed: ' . $e->getMessage()]);
        }

        return redirect()->route('admin.users.show', $newUser->id)
            ->with('success', 'Account for ' . $newUser->email . ' has been restored. A password reset email has been sent.');
    }

    /**
     * Suspend a user.
     */
    public function suspendUser(Request $request, $userId)
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:300']]);

        $user = User::findOrFail($userId);

        if ($user->user_type === 'admin') {
            return back()->withErrors(['error' => 'Admin accounts cannot be suspended.']);
        }

        $user->update([
            'suspended_at'      => now(),
            'suspension_reason' => $request->input('reason', 'Suspended by administrator.'),
        ]);

        if ($user->voter) {
            $user->voter->update(['is_active' => false]);
        }
        if ($user->politician) {
            $user->politician->update(['is_active' => false]);
        }

        return back()->with('success', 'User "' . $user->name . '" has been suspended.');
    }

    /**
     * Unsuspend a user.
     */
    public function unsuspendUser(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        $user->update([
            'suspended_at'      => null,
            'suspension_reason' => null,
        ]);

        if ($user->voter) {
            $user->voter->update(['is_active' => true]);
        }
        if ($user->politician) {
            $user->politician->update(['is_active' => true]);
        }

        // Notify user their account access has been restored (non-fatal)
        try {
            if ($user->email) {
                Mail::to($user->email)->queue(new AccountUnsuspendedMail($user));
            }

            $dashboardRoute = match ($user->user_type) {
                'admin' => route('admin.dashboard'),
                'politician' => route('politician.dashboard'),
                'voter' => route('voter.dashboard'),
                default => route('dashboard'),
            };

            $user->notify(new SystemAnnouncementNotification(
                'Your account has been reactivated',
                'An administrator restored your account access. You can now sign in and continue using the platform.',
                $dashboardRoute,
                'Open Dashboard'
            ));
        } catch (\Throwable $e) {
            Log::warning('Failed to send account unsuspension notifications', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'User "' . $user->name . '" has been unsuspended.');
    }
}
