<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Mail\GuestDigestConfirmationMail;
use App\Models\Voter;
use App\Services\GuestBoundaryMergeService;
use App\Services\MailingListService;
use App\Support\GuestBoundaryCookie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Inline "email me weekly updates about my saved places" opt-in shown on the
 * map after a guest favorites a district/city (see resources/js/map/ui/
 * digest-optin.js). Real voters just flip a preference; true guests get a
 * lightweight, unconfirmed Voter row (no User, no password) plus a double
 * opt-in email — the digest itself is never sent until that link is clicked.
 */
class GuestDigestOptInController extends Controller
{
    public function __construct(
        private readonly GuestBoundaryMergeService $mergeService,
        private readonly MailingListService $mailingListService,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $existingVoter = $request->user()?->voter;

        if ($existingVoter) {
            $existingVoter->user->notificationPreference()->firstOrCreate([])->update([
                'email_boundary_digest' => true,
            ]);

            $this->mailingListService->subscribe(
                $existingVoter->email,
                'map_favorites_digest',
                config('services.mailgun.map_favorites_list')
            );

            return response()->json(['ok' => true, 'status' => 'confirmed']);
        }

        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $voter = $this->resolveOrCreatePendingVoter($request, $data['email']);

        $cookieItems = GuestBoundaryCookie::readItems($request);
        if ($cookieItems !== []) {
            $this->mergeService->mergeCookieItemsIntoVoter($cookieItems, $voter);
        }

        $voter->update(['digest_confirmation_sent_at' => now()]);

        $confirmUrl = URL::temporarySignedRoute(
            'map.boundaries.digest.confirm',
            now()->addDays(3),
            [
                'voter' => $voter->uuid,
                'hash' => sha1($voter->email),
            ]
        );

        if (GuestDigestConfirmationMail::isEnabled()) {
            Mail::to($voter->email)->queue(new GuestDigestConfirmationMail($voter, $confirmUrl));
        }

        Cookie::queue(GuestBoundaryCookie::makeVoterCookie($voter->uuid, $request));
        Cookie::queue(GuestBoundaryCookie::forgetBoundariesCookie());

        return response()->json(['ok' => true, 'status' => 'confirmation_sent']);
    }

    public function confirm(Request $request, Voter $voter, string $hash)
    {
        if (
            $voter->user_id !== null
            || ! $voter->digest_opt_in_pending
            || ! hash_equals(sha1((string) $voter->email), $hash)
        ) {
            abort(403, 'Invalid or expired confirmation link.');
        }

        if ($voter->digest_confirmed_at === null) {
            $voter->update(['digest_confirmed_at' => now()]);

            $this->mailingListService->subscribe(
                $voter->email,
                'map_favorites_digest',
                config('services.mailgun.map_favorites_list')
            );
        }

        return view('standalone.public.digest-confirmed');
    }

    /**
     * One-click unsubscribe link included in every weekly digest sent to a
     * guest (user_id-null) voter — see BoundaryDigestMail/GuestDigestOptInController::store.
     * Idempotent: clicking it again after already unsubscribing just re-shows
     * the same confirmation page instead of erroring.
     */
    public function unsubscribe(Request $request, Voter $voter, string $hash)
    {
        if (
            $voter->user_id !== null
            || ! hash_equals(sha1((string) $voter->email), $hash)
        ) {
            abort(403, 'Invalid unsubscribe link.');
        }

        if ($voter->digest_opt_in_pending) {
            $voter->update([
                'digest_opt_in_pending' => false,
                'digest_confirmed_at' => null,
            ]);

            $this->mailingListService->unsubscribe(
                $voter->email,
                config('services.mailgun.map_favorites_list')
            );
        }

        return view('standalone.public.digest-unsubscribed');
    }

    private function resolveOrCreatePendingVoter(Request $request, string $email): Voter
    {
        $uuid = GuestBoundaryCookie::readVoterUuid($request);

        if ($uuid !== null) {
            $pending = Voter::whereNull('user_id')->where('uuid', $uuid)->first();

            if ($pending) {
                if ($pending->email !== $email) {
                    $pending->update([
                        'email' => $email,
                        'digest_opt_in_pending' => true,
                        'digest_confirmed_at' => null,
                    ]);
                } elseif (! $pending->digest_opt_in_pending) {
                    $pending->update(['digest_opt_in_pending' => true]);
                }

                return $pending;
            }
        }

        $byEmail = Voter::whereNull('user_id')->where('email', $email)->first();

        if ($byEmail) {
            if (! $byEmail->digest_opt_in_pending) {
                $byEmail->update(['digest_opt_in_pending' => true]);
            }

            return $byEmail;
        }

        return Voter::create([
            'full_name' => 'Guest Subscriber',
            'email' => $email,
            'is_active' => true,
            'is_verified' => false,
            'digest_opt_in_pending' => true,
        ]);
    }
}
