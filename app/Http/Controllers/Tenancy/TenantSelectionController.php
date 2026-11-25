<?php

namespace App\Http\Controllers\Tenancy;

use App\Domain\Audit\AuditLogger;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the tenant selection screen (users with zero or multiple
 * memberships) and the actual switch. The only source of a valid tenant is
 * an active membership of the authenticated user — a tampered tenant_id in
 * the request is rejected with 403 (spec phase 01 acceptance).
 */
class TenantSelectionController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    /** Selection screen shown when no tenant is resolvable. */
    public function select(Request $request): Response
    {
        $user = $request->user();

        $memberships = $user->activeMemberships()
            ->map(fn (TenantUser $membership): array => [
                'tenant_id' => $membership->tenant_id,
                'tenant_name' => $membership->tenant?->name,
                'role' => $membership->role->value,
            ]);

        return Inertia::render('panel/SelectTenant', [
            'memberships' => $memberships,
        ]);
    }

    public function switchTenant(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'string', 'size:26'],
        ]);

        $user = $request->user();

        $membership = TenantUser::query()
            ->where('user_id', $user->getKey())
            ->where('tenant_id', $validated['tenant_id'])
            ->where('status', 'active')
            ->whereHas('tenant', fn ($query) => $query->where('status', 'active'))
            ->with('tenant')
            ->first();

        if ($membership === null) {
            // Cross-tenant attempt or stale identifier — no details leaked.
            abort(403);
        }

        $tenant = $membership->tenant;

        if (! $tenant instanceof Tenant) {
            abort(403);
        }

        $request->session()->put('current_tenant_id', $membership->tenant_id);
        $request->session()->regenerate();

        $this->context->set($tenant, $membership);

        $this->audit->log(
            action: 'tenant.switched',
            tenant: $tenant,
            actor: $user,
            entityType: 'tenant',
            entityId: $membership->tenant_id,
            metadata: ['role' => $membership->role->value],
        );

        return redirect()->intended(route('dashboard'));
    }
}
