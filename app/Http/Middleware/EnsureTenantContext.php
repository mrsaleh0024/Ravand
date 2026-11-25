<?php

namespace App\Http\Middleware;

use App\Domain\Identity\UserStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\TenantUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current tenant from the authenticated user's VERIFIED
 * membership — never from a client-supplied tenant identifier (spec 2.2).
 *
 * Resolution order:
 *  1. tenant id stored in the server-side session, validated against an
 *     active membership in an active tenant;
 *  2. auto-selection when the user has exactly one active membership;
 *  3. otherwise redirect to the tenant selection screen.
 */
class EnsureTenantContext
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return Redirect::route('login');
        }

        // Suspended/archived users must not keep panel access even with a
        // valid session — enforce server-side, not via UI (spec 8.2).
        if ($user->status !== UserStatus::Active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return Redirect::route('login')->with('status', __('auth.suspended'));
        }

        $membership = null;

        $sessionTenantId = $request->session()->get('current_tenant_id');

        if (is_string($sessionTenantId) && $sessionTenantId !== '') {
            $membership = TenantUser::query()
                ->where('user_id', $user->getKey())
                ->where('tenant_id', $sessionTenantId)
                ->where('status', 'active')
                ->whereHas('tenant', fn ($query) => $query->where('status', 'active'))
                ->with('tenant')
                ->first();

            // Revoked membership / suspended tenant: forget immediately so the
            // access ends in the same request (no stale session grant).
            if ($membership === null) {
                $request->session()->forget('current_tenant_id');
            }
        }

        if ($membership === null) {
            $memberships = $user->activeMemberships();

            if ($memberships->count() === 1) {
                $membership = $memberships->first();
                $request->session()->put('current_tenant_id', $membership->tenant_id);
            }
        }

        if ($membership === null) {
            // Zero memberships, or multiple ones without an explicit choice.
            return Redirect::route('tenant.index');
        }

        $this->context->set($membership->tenant, $membership);

        return $next($request);
    }
}
