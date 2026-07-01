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
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust Railway proxies for proper HTTPS URL generation
        $middleware->trustProxies(at: '*');

        $middleware->appendToGroup('web', [
            \App\Http\Middleware\CaptureReferralContext::class,
            \App\Http\Middleware\CaptureEarlyBankReferral::class,
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
            'admin.2fa' => \App\Http\Middleware\EnsureAdminTwoFactorVerified::class,
            'no.cache' => \App\Http\Middleware\DisableAuthPageCache::class,
            'earlybank.api' => \App\Http\Middleware\EarlyBankApiAuth::class,
        ]);
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
    })->create();
