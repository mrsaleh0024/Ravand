<?php

namespace Tests\Feature\Tenancy;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTenancy;
use Tests\TestCase;

/**
 * Tenant switching smoke checks (spec phase 01): multi-membership owners
 * pick the active tenant; the choice lives in the server session only.
 */
class TenantSwitchTest extends TestCase
{
    use CreatesTenancy, RefreshDatabase;

    public function test_user_with_multiple_memberships_must_pick_one(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user, 'فروشگاه الف');
        $tenantB = $this->createTenantFor($user, 'فروشگاه ب');

        $this->actingAs($user);

        $this->get(route('dashboard'))->assertRedirect(route('tenant.index'));
        $this->get(route('tenant.index'))->assertOk();

        $this->post(route('tenant.store'), ['tenant_id' => $tenantB->id])
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertOk();

        $this->assertSame($tenantB->id, session('current_tenant_id'));
    }

    public function test_switch_to_tenant_without_membership_is_forbidden(): void
    {
        [$ownerA, $tenantA] = $this->createOwner('فروشگاه الف');
        [$otherOwner, $tenantB] = $this->createOwner('فروشگاه ب');

        $this->actingAs($ownerA);
        $this->withCurrentTenant($ownerA, $tenantA);

        $this->post(route('tenant.store'), ['tenant_id' => $tenantB->id])
            ->assertForbidden();

        $this->assertSame($tenantA->id, session('current_tenant_id'));
    }

    public function test_switch_is_audited(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user, 'فروشگاه الف');
        $tenantB = $this->createTenantFor($user, 'فروشگاه ب');

        $this->actingAs($user);

        $this->post(route('tenant.store'), ['tenant_id' => $tenantA->id])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tenant.switched',
            'tenant_id' => $tenantA->id,
            'actor_user_id' => $user->id,
        ]);

        $this->post(route('tenant.store'), ['tenant_id' => $tenantB->id])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tenant.switched',
            'tenant_id' => $tenantB->id,
        ]);

        $this->assertSame(2, AuditLog::query()->where('action', 'tenant.switched')->count());
    }

    public function test_auto_selection_when_single_membership(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه تنها');

        $this->actingAs($owner);

        $this->get(route('dashboard'))->assertOk();
        $this->assertSame($tenant->id, session('current_tenant_id'));
    }

    private function createTenantFor(User $user, string $name): Tenant
    {
        $tenant = Tenant::query()->create([
            'name' => $name,
            'slug' => (Str::slug($name) ?: 'tenant').'-'.Str::lower(Str::random(6)),
        ]);

        $this->attachMembership($user, $tenant, 'owner');

        return $tenant;
    }
}
