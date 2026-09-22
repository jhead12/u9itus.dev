<?php

namespace App\Http\Middleware;

use App\Support\AdminAccess;
use Closure;
use Illuminate\Http\Request;

class AuthorizeAdminAccess
{
    public function handle(Request $request, Closure $next)
    {
        $name = (string) $request->route()?->getName();
        if (! $request->is('admin', 'admin/*', 'api/v1/admin', 'api/v1/admin/*')
            || in_array($name, ['admin.login', 'admin.login.submit'], true)) {
            return $next($request);
        }
        $user = $request->user();
        if (! $user) {
            return $request->expectsJson() ? abort(401) : redirect()->route('login');
        }
        $user->unsetRelation('roles')->unsetRelation('permissions');
        abort_if($request->has('action') && ! is_string($request->input('action')), 422);
        abort_unless(AdminAccess::canRoute($user, $name, $request->input('action')), 403);
        if (str_starts_with($name, 'admin.onboarding.') && ! AdminAccess::owner($user)) {
            return redirect()->route('admin.dashboard');
        }
        return $next($request);
    }
}
