<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/wix.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust Railway proxies for proper HTTPS URL generation
        $middleware->trustProxies(at: '*');
        
        $middleware->alias([
            'wix.verify'        => \App\Http\Middleware\VerifyWixInstance::class,
            'redirect.wix'      => \App\Http\Middleware\RedirectIfWix::class,
            'role'              => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'        => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission'=> \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        // Wix routes are loaded via iframes — CSRF tokens are unavailable.
        // Signature verification is handled by VerifyWixInstance middleware instead.
        $middleware->validateCsrfTokens(except: [
            'wix/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
