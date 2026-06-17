<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Mail\ProfileClaimAdminAlertMail;
use App\Mail\ProfileClaimRequestMail;
use App\Models\Politician;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Handles the "Claim this profile" flow for unclaimed politician profiles.
 *
 * Flow:
 *  1. GET  /p/{slug}/claim        — Show the claim request form
 *  2. POST /p/{slug}/claim        — Validate email, store token, email the link
 *  3. GET  /p/{slug}/claim/verify — Claimant clicks link → redirected to register as politician
 */
class ProfileClaimController extends Controller
{
    /** Token TTL in hours */
    private const TOKEN_TTL_HOURS = 48;

    // ── Step 1: Show claim form ────────────────────────────────────────────

    public function show(string $slug): View
    {
        $politician = $this->resolveUnclaimed($slug);

        return view('standalone.public.claim-profile', compact('politician'));
    }

    // ── Step 2: Submit claim request — rate-limited, sends email ──────────

    public function submit(Request $request, string $slug): RedirectResponse
    {
        $politician = $this->resolveUnclaimed($slug);

        // Rate limit: 3 requests per email per hour
        $limiterKey = 'profile-claim:' . sha1($request->ip() . '|' . $slug);
        if (RateLimiter::tooManyAttempts($limiterKey, 3)) {
            return back()->withErrors(['email' => 'Too many requests. Please wait before trying again.']);
        }
        RateLimiter::hit($limiterKey, 3600);

        $data = $request->validate([
            'email'      => ['required', 'email:rfc,dns', 'max:255'],
            'full_name'  => ['required', 'string', 'max:255'],
        ]);

        $token    = Str::random(64);
        $claimUrl = route('politician.profile.claim.verify', [
            'slug'  => $slug,
            'token' => $token,
        ]);

        $politician->update([
            'claim_email'        => $data['email'],
            'claim_token'        => $token,
            'claim_requested_at' => now(),
        ]);

        Mail::to($data['email'])->send(new ProfileClaimRequestMail($politician, $claimUrl));

        return redirect()
            ->route('politician.public.show', $slug)
            ->with('claim_submitted', true);
    }

    // ── Step 3: Token verification — redirect to politician registration ───

    public function verify(Request $request, string $slug): RedirectResponse
    {
        $politician = Politician::where('slug', $slug)
            ->whereNull('user_id')
            ->whereNotNull('claim_token')
            ->first();

        if (! $politician) {
            return redirect()->route('politician.public.show', $slug)
                ->withErrors(['claim' => 'This claim link is invalid or has already been used.']);
        }

        $token = (string) $request->query('token', '');

        // Constant-time comparison to prevent timing attacks
        if (! hash_equals((string) $politician->claim_token, $token)) {
            return redirect()->route('politician.public.show', $slug)
                ->withErrors(['claim' => 'Invalid claim token.']);
        }

        // Check token expiry
        if (
            $politician->claim_requested_at === null ||
            $politician->claim_requested_at->lt(now()->subHours(self::TOKEN_TTL_HOURS))
        ) {
            $politician->update(['claim_token' => null, 'claim_requested_at' => null]);

            return redirect()->route('politician.profile.claim.show', $slug)
                ->withErrors(['claim' => 'This verification link has expired. Please request a new one.']);
        }

        // Alert the admin
        $adminEmail = config('mail.admin_address', config('mail.from.address'));
        $adminUrl   = url('/admin/politicians/' . $politician->id);

        Mail::to($adminEmail)->send(new ProfileClaimAdminAlertMail(
            politician:      $politician,
            claimantEmail:   (string) $politician->claim_email,
            adminProfileUrl: $adminUrl,
        ));

        // Clear the token so it cannot be reused, store the verified email
        $politician->update([
            'claim_token'        => null,
            'claim_requested_at' => null,
            'verification_email' => $politician->claim_email,
        ]);

        // Pre-fill registration with their verified email and tie back to this profile
        $registrationUrl = route('register.politician', [
            'claim_slug'  => $slug,
            'claim_email' => $politician->claim_email,
        ]);

        return redirect($registrationUrl)
            ->with('claim_verified', $politician->full_name);
    }

    // ── Helper ────────────────────────────────────────────────────────────

    private function resolveUnclaimed(string $slug): Politician
    {
        $politician = Politician::where('slug', $slug)
            ->whereNull('user_id')
            ->where('page_published', true)
            ->firstOrFail();

        return $politician;
    }
}
