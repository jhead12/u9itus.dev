<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RememberContributorDestination
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->isMethod('GET') && $response instanceof RedirectResponse
            && in_array($response->getTargetUrl(), [route('2fa.challenge'), route('2fa.setup'), route('admin.2fa.challenge'), route('admin.2fa.setup')], true)) {
            // Login consumes its intended URL; restore it if 2FA interrupts the clip form next.
            $request->session()->put('url.intended', route('contributor.chatter.index'));
        }
        return $response;
    }
}
