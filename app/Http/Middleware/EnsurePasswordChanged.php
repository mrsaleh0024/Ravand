<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locks the whole panel behind a mandatory password change (ADR-021).
 *
 * When `must_change_password` is set (a temporary password was issued by the
 * owner/manager), every authenticated route is redirected to the
 * force-change screen until a new password is saved. Only the change
 * endpoints and logout stay reachable.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->must_change_password) {
            return $next($request);
        }

        $allowed = ['password.force.show', 'password.force.update', 'logout'];

        if ($request->routeIs($allowed)) {
            return $next($request);
        }

        return Redirect::route('password.force.show');
    }
}
