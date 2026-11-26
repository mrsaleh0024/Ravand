<?php

namespace Tests\Concerns;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Shared helpers for tenant smoke tests (phase 01).
 */
trait CreatesTenancy
{
    protected function createOwner(string $tenantName = 'فروشگاه الف'): array
    {
        [$user, $tenant] = $this->createTenantWithUser($tenantName, 'owner');

        return [$user, $tenant];
    }

    protected function createTenantWithUser(string $tenantName, string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => $tenantName,
            'slug' => (Str::slug($tenantName) ?: 'tenant').'-'.Str::lower(Str::random(6)),
        ]);

        $user = User::factory()->create();

        $this->attachMembership($user, $tenant, $role);

        return [$user, $tenant];
    }

    protected function attachMembership(User $user, Tenant $tenant, string $role): TenantUser
    {
        return TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }

    /** Establish a session tenant context for the given tenant (simulates prior selection). */
    protected function withCurrentTenant(User $user, Tenant $tenant): static
    {
        $this->session(['current_tenant_id' => $tenant->id]);

        return $this;
    }
}
