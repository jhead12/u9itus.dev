<?php

namespace App\Http\Controllers\Wix;

use App\Http\Controllers\Controller;
use App\Services\WixOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handles the Wix OAuth installation flow.
 *
 * Flow:
 *   1. Wix sends user to our app URL with ?token=xxx
 *   2. We redirect them to Wix consent screen
 *   3. Wix redirects back to /wix/oauth/callback with ?code=xxx&instanceId=xxx
 *   4. We exchange the code for access + refresh tokens
 *   5. We store them and redirect to the dashboard
 */
class OAuthController extends Controller
{
    public function __construct(
        protected WixOAuthService $wixOAuth,
    ) {}

    /**
     * Entry point — Wix redirects here when the site owner clicks "Add to Site."
     * We redirect them to the Wix consent screen.
     */
    public function install(Request $request)
    {
        $token = $request->query('token');

        if (!$token) {
            return response()->json(['error' => 'Missing token parameter'], 400);
        }

        $consentUrl = $this->wixOAuth->getConsentUrl($token);

        return redirect($consentUrl);
    }

    /**
     * Wix redirects here after the site owner grants consent.
     * We exchange the auth code for tokens.
     */
    public function callback(Request $request)
    {
        $code       = $request->query('code');
        $instanceId = $request->query('instanceId');

        if (!$code || !$instanceId) {
            return response()->json(['error' => 'Missing code or instanceId'], 400);
        }

        try {
            $tokenData = $this->wixOAuth->exchangeCodeForTokens($code);
            $site      = $this->wixOAuth->createOrUpdateSite($instanceId, $tokenData);

            Log::info("Wix OAuth completed for instance {$instanceId}");

            // Redirect to the dashboard page within Wix
            return redirect()->route('wix.dashboard.index');
        } catch (\Throwable $e) {
            Log::error('Wix OAuth callback failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'OAuth failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Signup URL — shown when a Wix user needs to create an account on our side
     * before installing the app.
     */
    public function signup(Request $request)
    {
        return view('wix.signup', [
            'instance' => $request->query('instance'),
        ]);
    }
}
