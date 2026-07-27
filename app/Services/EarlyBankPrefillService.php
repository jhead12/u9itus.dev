<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * EarlyBankPrefillService
 *
 * Decodes the signed `data`/`ts`/`sig` query params Early-bank attaches to
 * the "Complete U9itus registration" link (built in earlybank's
 * EnrollmentController::redirectToU9itus) so the registration form can be
 * pre-filled with name/email/phone/address/DOB the member already gave
 * Early-bank (some of it pulled from their Stripe Connect account) instead
 * of asking them to retype it.
 *
 * Uses the same shared secret + HMAC-SHA256 scheme as the reverse SSO hop
 * (ManagesVoterAuxiliaryActions::earlyBankSso()), just verified instead of
 * signed. Never trusts unsigned name/email/etc. — only decodes claims once
 * the signature and timestamp check out.
 */
class EarlyBankPrefillService
{
    /** Must match earlybank's EnrollmentController::MAX_SKEW_SECONDS. */
    private const MAX_SKEW_SECONDS = 300;

    /**
     * Returns the decoded claims (member_uuid, name, email, and — if the
     * member has completed Stripe Connect onboarding on earlybank —
     * phone/address_1/address_2/city/state/zip/dob_day/dob_month/dob_year),
     * or an empty array if the link is missing, expired, or tampered with.
     */
    public function decode(Request $request): array
    {
        $data = (string) $request->query('data', '');
        $ts   = (string) $request->query('ts', '');
        $sig  = (string) $request->query('sig', '');
        $secret = (string) config('services.earlybank.webhook_secret', '');

        if ($data === '' || $ts === '' || $sig === '' || $secret === '') {
            return [];
        }

        if (abs(time() - (int) $ts) > self::MAX_SKEW_SECONDS) {
            return [];
        }

        $expected = hash_hmac('sha256', $data . '.' . $ts, $secret);
        if (! hash_equals($expected, $sig)) {
            return [];
        }

        $padded = strtr($data, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);

        $json = base64_decode($padded, true);
        if ($json === false) {
            return [];
        }

        $claims = json_decode($json, true);

        return is_array($claims) ? $claims : [];
    }
}
