<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\TenantContext;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * Values are plain closures: Inertia resolves them at render time, i.e.
     * after the `tenant` middleware has resolved the verified membership.
     * Only minimal, non-sensitive data is exposed (spec section 12).
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => fn () => $request->user()?->only(['id', 'name', 'phone']),
                'role' => fn () => app(TenantContext::class)->role()?->value,
                'memberships' => function () use ($request) {
                    $user = $request->user();

                    if ($user === null) {
                        return [];
                    }

                    return $user->activeMemberships()
                        ->map(fn (TenantUser $membership): array => [
                            'tenant_id' => $membership->tenant_id,
                            'tenant_name' => $membership->tenant?->name,
                            'role' => $membership->role->value,
                        ]);
                },
            ],
            'tenant' => function () {
                $context = app(TenantContext::class);

                if (! $context->has()) {
                    return null;
                }

                /** @var Tenant $tenant */
                $tenant = $context->tenant();

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'default_currency' => $tenant->default_currency,
                    'timezone' => $tenant->timezone,
                ];
            },
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
