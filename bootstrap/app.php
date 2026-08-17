<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust Railway proxies for proper HTTPS URL generation
        $middleware->trustProxies(at: '*');

        $middleware->appendToGroup('web', [
            \App\Http\Middleware\CaptureReferralContext::class,
            \App\Http\Middleware\CaptureEarlyBankReferral::class,
            \App\Http\Middleware\MergeGuestFavoriteBoundaries::class,
            \App\Http\Middleware\InjectAnalyticsTags::class,
        ]);
        
        $middleware->alias([
            'role'              => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'        => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission'=> \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'check.role'        => \App\Http\Middleware\CheckUserRole::class,
            'check.voter.onboarding' => \App\Http\Middleware\CheckVoterOnboarding::class,
            'check.politician.onboarding' => \App\Http\Middleware\CheckPoliticianOnboarding::class,
            'check.citizen.onboarding' => \App\Http\Middleware\CheckCitizenOnboarding::class,
            'check.admin.onboarding' => \App\Http\Middleware\CheckAdminOnboarding::class,
            // Admins get a dedicated setup/challenge flow inside the dashboard
            // security settings (columns: admin_two_factor_*, config key:
            // platform.standalone.auth.admin_2fa.*). Voters/politicians/citizens
            // share a separate generic TOTP flow (columns: two_factor_*, config
            // key: platform.standalone.auth.two_factor.*). The two are enforced,
            // configured, and TTL'd independently — see EnsureAdminTwoFactorVerified
            // vs EnsureTwoFactorVerified.
            'admin.2fa' => \App\Http\Middleware\EnsureAdminTwoFactorVerified::class,
            '2fa'       => \App\Http\Middleware\EnsureTwoFactorVerified::class,
            'no.cache' => \App\Http\Middleware\DisableAuthPageCache::class,
            'earlybank.api' => \App\Http\Middleware\EarlyBankApiAuth::class,
            // SEC-4: stateless voter API bearer-token auth + ownership checks.
            'voter-token' => \App\Http\Middleware\AuthenticateVoterToken::class,
            'voter.owns' => \App\Http\Middleware\EnsureVoterTokenMatches::class,
            // Guest Trial Mode: silently provisions an anonymous visitor into
            // a flagged voter session; blocks that session from money routes.
            'guest.trial' => \App\Http\Middleware\ProvisionGuestVoterSession::class,
            'block.guest.money' => \App\Http\Middleware\BlockGuestFromMonetization::class,
        ]);

        // Laravel's SortedMiddleware always runs framework middleware present
        // in its priority list (Authenticate/`auth` among them) before any
        // unlisted middleware, regardless of declared order in the route
        // group — so `guest.trial` needs an explicit priority slot ahead of
        // `auth`, or its provisioning would never run before `auth` redirects
        // an anonymous visitor to /login.
        $middleware->prependToPriorityList(
            // Laravel's default priority list carries the *contract*
            // (`AuthenticatesRequests`), not the concrete `Authenticate`
            // class the `auth` alias resolves to — targeting the concrete
            // class here silently no-ops (falls through to append-at-end).
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: \App\Http\Middleware\ProvisionGuestVoterSession::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $safeLogoutRedirect = function (Request $request) {
            if (Auth::check()) {
                Auth::guard('web')->logout();
            }

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('status', 'Your session expired. You have been signed out. Please sign in again.');
        };

        $safePageExpiredRedirect = function (Request $request) {
            $target = url()->previous();

            if (empty($target) || $target === $request->fullUrl()) {
                $target = route('login');
            }

            return redirect()
                ->to($target)
                ->withInput($request->except(['_token', 'password', 'password_confirmation']))
                ->withErrors(['session' => 'Page expired. Please refresh and try again.']);
        };

        $exceptions->render(function (TokenMismatchException $e, Request $request) use ($safeLogoutRedirect) {
            if ($request->isMethod('POST') && ($request->routeIs('logout') || $request->is('logout'))) {
                return $safeLogoutRedirect($request);
            }

            if ($request->isMethod('POST') && !$request->expectsJson()) {
                return $safePageExpiredRedirect($request);
            }

            return null;
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($safeLogoutRedirect, $safePageExpiredRedirect) {
            if (
                $e->getStatusCode() === 419
                && $request->isMethod('POST')
                && ($request->routeIs('logout') || $request->is('logout'))
            ) {
                return $safeLogoutRedirect($request);
            }

            if ($e->getStatusCode() === 419 && $request->isMethod('POST') && !$request->expectsJson()) {
                return $safePageExpiredRedirect($request);
            }

            return null;
        });

        // ── Profile 500 auto-repair ──────────────────────────────────────────
        // When an unhandled exception occurs on a public politician profile page
        // (/p/{slug}), dispatch a background job that triggers the GitHub
        // repair-broken-profile workflow via repository_dispatch.
        //
        // Only fires for genuine server errors (non-HttpException) so 404s and
        // 419s are not reported. Rate-limited per slug (30 min) in the job.
        $exceptions->report(function (\Throwable $e) {
            // Skip HTTP exceptions (404, 419, 403, …) — those are expected.
            if ($e instanceof HttpExceptionInterface) {
                return false; // let the default reporter continue
            }

            $request = request();

            if (! $request || ! $request->isMethod('GET')) {
                return false;
            }

            $path = ltrim($request->path(), '/');

            // Match /p/{slug} only — no sub-pages like /p/{slug}/news or /p/{slug}/claim.
            if (! preg_match('#^p/([^/]+)$#', $path, $m)) {
                return false;
            }

            $slug = $m[1];

            // Dispatch asynchronously so the exception response is not delayed.
            \App\Jobs\DispatchProfileRepairWorkflow::dispatch($slug, '/' . $path)
                ->onQueue('default')
                ->afterCommit();

            return false; // allow the default exception reporter to also log it
        });
    })->create();
