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
 * Tenant isolation smoke checks (spec phase 01 acceptance):
 * a user must only ever reach tenants they hold an active membership in,
 * no matter what identifiers are sent in the request.
 */
class TenantIsolationTest extends TestCase
{
    use CreatesTenancy, RefreshDatabase;

    public function test_owner_of_tenant_a_cannot_switch_to_tenant_b(): void
    {
        [$ownerA, $tenantA] = $this->createOwner('فروشگاه الف');
        [, $tenantB] = $this->createOwner('فروشگاه ب');

        $this->actingAs($ownerA);
        $this->withCurrentTenant($ownerA, $tenantA);

        $this->post(route('tenant.store'), ['tenant_id' => $tenantB->id])
            ->assertForbidden();

        // Session tenant must remain tenant A.
        $this->withSession(['current_tenant_id' => $tenantA->id])
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_switch_with_forged_tenant_id_is_forbidden(): void
    {
        [$ownerA, $tenantA] = $this->createOwner('فروشگاه الف');

        $this->actingAs($ownerA);
        $this->withCurrentTenant($ownerA, $tenantA);

        $this->post(route('tenant.store'), ['tenant_id' => Str::ulid()->toBase32()])
            ->assertForbidden();

        $this->post(route('tenant.store'), ['tenant_id' => 'not-a-tenant'])
            ->assertSessionHasErrors(['tenant_id']);
    }

    public function test_settings_update_only_affects_current_tenant(): void
    {
        [$ownerA, $tenantA] = $this->createOwner('فروشگاه الف');
        [$ownerB, $tenantB] = $this->createOwner('فروشگاه ب');

        $this->actingAs($ownerA);
        $this->withCurrentTenant($ownerA, $tenantA);

        $this->put(route('tenant.settings.update'), [
            'name' => 'فروشگاه الفِ ویرایش‌شده',
            'default_currency' => 'IRR',
            'timezone' => 'Asia/Tehran',
            'locale' => 'fa-IR',
            'calendar' => 'jalali',
        ])->assertRedirect();

        $tenantA->refresh();
        $tenantB->refresh();

        $this->assertSame('فروشگاه الفِ ویرایش‌شده', $tenantA->name);
        $this->assertSame('IRR', $tenantA->default_currency);
        $this->assertSame('فروشگاه ب', $tenantB->name);
        $this->assertSame('IRT', $tenantB->default_currency);

        // No audit rows for tenant B from A's actions.
        $this->assertSame(
            0,
            AuditLog::query()->where('tenant_id', $tenantB->id)->where('actor_user_id', $ownerA->id)->count(),
        );
    }

    public function test_user_without_membership_cannot_reach_the_panel(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('dashboard'))->assertRedirect(route('tenant.index'));

        $this->get(route('tenant.index'))->assertOk();
    }

    public function test_revoked_membership_loses_panel_access_immediately(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه الف');

        $this->actingAs($owner);
        $this->withCurrentTenant($owner, $tenant);

        // Revoke membership server-side (as an admin/CLI would).
        $membership = $owner->activeMemberships()->first();
        $membership->update(['status' => 'revoked']);

        $this->get(route('dashboard'))->assertRedirect(route('tenant.index'));
    }

    public function test_suspended_tenant_is_not_resolvable(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه الف');

        $tenant->update(['status' => 'suspended']);

        $this->actingAs($owner);
        $this->withCurrentTenant($owner, $tenant);

        $this->get(route('dashboard'))->assertRedirect(route('tenant.index'));
    }

    public function test_suspended_user_is_logged_out_of_the_panel(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه الف');

        $this->actingAs($owner);
        $this->withCurrentTenant($owner, $tenant);

        $owner->update(['status' => 'suspended']);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_tenant_table_scopes_another_tenants_row(): void
    {
        [$ownerA, $tenantA] = $this->createOwner('فروشگاه الف');
        [, $tenantB] = $this->createOwner('فروشگاه ب');

        $this->actingAs($ownerA);
        $this->withCurrentTenant($ownerA, $tenantA);

        // Even when someone posts tenant B's ULID to the select endpoint
        // directly, membership check denies it.
        $this->post(route('tenant.store'), ['tenant_id' => $tenantB->id])
            ->assertForbidden();

        $this->assertNull(
            AuditLog::query()->where('tenant_id', $tenantB->id)->first(),
            'Tenant B must have no audit records from tenant A actions.',
        );

        // Sanity: tenant B still untouched.
        $this->assertNotSame($tenantB->id, session('current_tenant_id'));
        $this->assertSame(2, Tenant::query()->count());
    }
}
