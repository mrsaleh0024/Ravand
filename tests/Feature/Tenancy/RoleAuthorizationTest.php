<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenancy;
use Tests\TestCase;

/**
 * Role-based authorization smoke checks (spec phase 01):
 * owner > manager > operator. Owner-only endpoints must reject operators
 * and managers with server-side 403 — never only a hidden UI button.
 */
class RoleAuthorizationTest extends TestCase
{
    use CreatesTenancy, RefreshDatabase;

    public function test_operator_is_denied_owner_only_endpoints(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه الف');
        $operator = User::factory()->create();
        $this->attachMembership($operator, $tenant, 'operator');

        $this->actingAs($operator);
        $this->withCurrentTenant($operator, $tenant);

        $this->get(route('tenant.settings.edit'))->assertForbidden();
        $this->put(route('tenant.settings.update'), [
            'name' => 'نام جدید',
            'default_currency' => 'IRR',
            'timezone' => 'Asia/Tehran',
            'locale' => 'fa-IR',
            'calendar' => 'jalali',
        ])->assertForbidden();

        $this->get(route('tenant.audit.index'))->assertForbidden();

        // Operator can still use the shared dashboard.
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_manager_can_view_but_not_change_settings(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه الف');
        $manager = User::factory()->create();
        $this->attachMembership($manager, $tenant, 'manager');

        $this->actingAs($manager);
        $this->withCurrentTenant($manager, $tenant);

        $this->get(route('tenant.settings.edit'))->assertOk();
        $this->get(route('tenant.audit.index'))->assertOk();

        $this->put(route('tenant.settings.update'), [
            'name' => 'نام جدید',
            'default_currency' => 'IRR',
            'timezone' => 'Asia/Tehran',
            'locale' => 'fa-IR',
            'calendar' => 'jalali',
        ])->assertForbidden();
    }

    public function test_owner_can_view_and_change_settings(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه الف');

        $this->actingAs($owner);
        $this->withCurrentTenant($owner, $tenant);

        $this->get(route('tenant.settings.edit'))->assertOk();
        $this->get(route('tenant.audit.index'))->assertOk();

        $this->put(route('tenant.settings.update'), [
            'name' => 'فروشگاه الف جدید',
            'default_currency' => 'IRR',
            'timezone' => 'Asia/Tehran',
            'locale' => 'fa-IR',
            'calendar' => 'jalali',
        ])->assertRedirect();

        $this->assertSame('فروشگاه الف جدید', $tenant->refresh()->name);
    }

    public function test_role_change_takes_effect_without_session_relogin(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه الف');
        $operator = User::factory()->create();
        $membership = $this->attachMembership($operator, $tenant, 'operator');

        $this->actingAs($operator);
        $this->withCurrentTenant($operator, $tenant);

        $this->get(route('tenant.settings.edit'))->assertForbidden();

        // Promote to owner — access must apply immediately (context role is
        // re-evaluated per request from the membership row).
        $membership->update(['role' => 'owner']);

        $this->get(route('tenant.settings.edit'))->assertOk();
    }

    public function test_guests_cannot_access_tenant_settings(): void
    {
        $this->get(route('tenant.settings.edit'))->assertRedirect(route('login'));
        $this->get(route('tenant.audit.index'))->assertRedirect(route('login'));
    }
}
