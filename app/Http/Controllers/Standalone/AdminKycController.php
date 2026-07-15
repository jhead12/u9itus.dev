<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Mail\KycApprovedMail;
use App\Mail\KycRejectedMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Admin KYC review queue for politicians (voters verify via Stripe Connect).
 *
 * Split out of AdminController. Lists pending KYC, approves/rejects (with
 * schema-tolerant column writes and best-effort notification emails), and
 * serves a user's uploaded KYC document inline.
 */
class AdminKycController extends Controller
{
    /**
     * Show the KYC review queue.
     *
     * Lists politicians and voters with kyc_status = 'pending'.
     */
    public function kycQueue()
    {
        // Voter identity verification is handled via Stripe Connect; this queue
        // is restricted to politicians who upload government-issued ID documents.
        $users = User::with(['politician', 'voter'])
            ->where('kyc_status', 'pending')
            ->where('user_type', 'politician')
            ->latest()
            ->paginate(30);

        $stats = [
            'pending'  => User::where('kyc_status', 'pending')
                               ->where('user_type', 'politician')->count(),
            'approved' => User::where('kyc_status', 'approved')
                               ->where('user_type', 'politician')->count(),
            'rejected' => User::where('kyc_status', 'rejected')
                               ->where('user_type', 'politician')->count(),
        ];

        return view('standalone.admin.kyc', compact('users', 'stats'));
    }

    /**
     * Approve a user's KYC.
     */
    public function approveKyc(Request $request, $userId)
    {
        $user = User::with(['politician', 'voter'])->findOrFail($userId);

        // Voters use Stripe identity verification — manual admin KYC approval is not supported.
        if ($user->user_type === 'voter') {
            return back()->withErrors(['error' => 'Voter identity verification is handled via Stripe Connect and cannot be manually approved here.']);
        }

        try {
            $user->update([
                'kyc_status'      => 'approved',
                'is_verified'     => true,
                'kyc_reviewed_at' => now(),
                'kyc_reviewer_id' => auth()->id(),
            ]);
        } catch (\Exception $e) {
            // Fallback if kyc_reviewed_at or kyc_reviewer_id columns don't exist in staging
            Log::warning('KYC approval partial update (missing migration columns)', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            $user->update([
                'kyc_status'  => 'approved',
                'is_verified' => true,
            ]);
        }

        if ($user->politician) {
            $user->politician->update(['kyc_status' => 'approved', 'verified_official' => true]);
        }
        if ($user->voter) {
            $user->voter->update(['is_verified' => true]);
        }

        // Notify the user their KYC has been approved
        try {
            Mail::to($user->email)->queue(new KycApprovedMail($user));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to send KYC approved email', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'KYC approved for ' . $user->name . '.');
    }

    /**
     * Reject a user's KYC.
     */
    public function rejectKyc(Request $request, $userId)
    {
        try {
            $request->validate([
                'reason' => ['nullable', 'string', 'max:500'],
            ]);

            $user = User::with(['politician', 'voter'])->findOrFail($userId);

            // Voters use Stripe identity verification — manual admin KYC rejection is not supported.
            if ($user->user_type === 'voter') {
                return back()->withErrors(['error' => 'Voter identity verification is handled via Stripe Connect and cannot be manually rejected here.']);
            }
            $reason = (string) $request->input('reason', 'Identity could not be verified.');

            $userUpdate = [
                'kyc_status' => 'rejected',
            ];

            if (Schema::hasColumn('users', 'kyc_reviewed_at')) {
                $userUpdate['kyc_reviewed_at'] = now();
            }
            if (Schema::hasColumn('users', 'kyc_reviewer_id')) {
                $userUpdate['kyc_reviewer_id'] = auth()->id();
            }
            if (Schema::hasColumn('users', 'kyc_rejection_reason')) {
                $userUpdate['kyc_rejection_reason'] = $reason;
            }

            DB::table('users')->where('id', $user->id)->update($userUpdate);
            $user->refresh();

            if ($user->politician) {
                try {
                    if (Schema::hasColumn('politicians', 'kyc_status')) {
                        DB::table('politicians')
                            ->where('id', $user->politician->id)
                            ->update(['kyc_status' => 'rejected']);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to sync politician KYC rejection status', [
                        'user_id' => $user->id,
                        'politician_id' => $user->politician->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Notify the user their KYC has been rejected with the reason
            try {
                Mail::to($user->email)->queue(new KycRejectedMail($user, $reason));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to send KYC rejected email', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('KYC rejection failed', [
                'user_id' => $userId,
                'admin_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.kyc.index')
                ->withErrors(['error' => 'Unable to reject KYC right now. Please try again.']);
        }

        return redirect()
            ->route('admin.kyc.index')
            ->with('success', 'KYC rejected for ' . ($user->name ?: 'user') . '.');
    }

    /**
     * View a user's KYC document (admin only).
     */
    public function viewKycDocument(User $user)
    {
        if (!$user->kyc_document_path) {
            abort(404, 'No KYC document found for this user.');
        }

        $path = storage_path('app/public/' . $user->kyc_document_path);

        if (!file_exists($path)) {
            abort(404, 'KYC document file not found on server.');
        }

        $mimeType = mime_content_type($path);
        
        return response()->file($path, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        ]);
    }
}
