<?php

namespace Tests\Feature\Panel;

use App\Models\PasswordResetRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenancy;
use Tests\TestCase;

/**
 * Member management (ADR-022) + forced password change (ADR-021).
 */
class MemberManagementTest extends TestCase
{
    use CreatesTenancy, RefreshDatabase;

    public function test_owner_creates_a_manager_with_temp_password(): void
    {
        [$owner, $tenant] = $this->createOwner();

        $response = $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('panel.members.store'), [
                'name' => 'مدیر فروشگاه',
                'phone' => '+98 912 333 44 55',
                'role' => 'manager',
            ]);

        $response->assertSessionHasNoErrors();

        $manager = User::query()->where('phone', '09123334455')->firstOrFail();
        $this->assertTrue($manager->must_change_password);

        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $tenant->id,
            'user_id' => $manager->id,
            'role' => 'manager',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'member.created', 'tenant_id' => $tenant->id]);
    }

    public function test_manager_can_create_operators_but_not_managers(): void
    {
        [$owner, $tenant] = $this->createOwner();
        $manager = User::factory()->create();
        $this->attachMembership($manager, $tenant, 'manager');

        // Manager creates an operator — allowed.
        $this->actingAs($manager)
            ->withCurrentTenant($manager, $tenant)
            ->post(route('panel.members.store'), [
                'name' => 'اپراتور یک',
                'phone' => '09124440000',
                'role' => 'operator',
            ])
            ->assertSessionHasNoErrors();

        // Manager tries to create a manager — forbidden.
        $this->actingAs($manager)
            ->withCurrentTenant($manager, $tenant)
            ->post(route('panel.members.store'), [
                'name' => 'مدیر دوم',
                'phone' => '09124441111',
                'role' => 'manager',
            ])
            ->assertForbidden();
    }

    public function test_owner_can_suspend_and_remove_members(): void
    {
        [$owner, $tenant] = $this->createOwner();
        $operator = User::factory()->create();
        $membership = $this->attachMembership($operator, $tenant, 'operator');

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->patch(route('panel.members.toggle', $membership))
            ->assertRedirect();

        $this->assertDatabaseHas('tenant_user', ['id' => $membership->id, 'status' => 'suspended']);

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->delete(route('panel.members.destroy', $membership))
            ->assertRedirect();

        $this->assertModelMissing($membership);
    }

    public function test_manager_cannot_suspend_members(): void
    {
        [$owner, $tenant] = $this->createOwner();
        $manager = User::factory()->create();
        $this->attachMembership($manager, $tenant, 'manager');
        $operator = User::factory()->create();
        $membership = $this->attachMembership($operator, $tenant, 'operator');

        $this->actingAs($manager)
            ->withCurrentTenant($manager, $tenant)
            ->patch(route('panel.members.toggle', $membership))
            ->assertForbidden();
    }

    public function test_owner_can_issue_temp_password_to_an_existing_member(): void
    {
        [$owner, $tenant] = $this->createOwner();
        $operator = User::factory()->create();
        $membership = $this->attachMembership($operator, $tenant, 'operator');

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('panel.members.reset-password', $membership))
            ->assertRedirect();

        $operator->refresh();
        $this->assertTrue($operator->must_change_password);
    }

    public function test_temp_password_login_forces_password_change(): void
    {
        [$owner, $tenant] = $this->createOwner();
        $operator = User::factory()->mustChangePassword()->create();
        $this->attachMembership($operator, $tenant, 'operator');

        $this->post(route('login.store'), [
            'phone' => $operator->phone,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($operator);

        // Every panel route redirects to the force-change screen.
        $this->get(route('dashboard'))->assertRedirect(route('password.force.show'));
        $this->get(route('panel.members.index'))->assertRedirect(route('password.force.show'));

        // Setting a new password releases the lock.
        $this->put(route('password.force.update'), [
            'password' => 'N3w-Secure-Passw0rd',
            'password_confirmation' => 'N3w-Secure-Passw0rd',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertFalse($operator->refresh()->must_change_password);
        $this->get(route('dashboard'))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.password_changed']);
    }

    public function test_new_password_must_differ_from_temp_password(): void
    {
        [$owner, $tenant] = $this->createOwner();
        $operator = User::factory()->mustChangePassword()->create(['password' => 'TempPassw0rdXY']);
        $this->attachMembership($operator, $tenant, 'operator');

        $this->post(route('login.store'), [
            'phone' => $operator->phone,
            'password' => 'TempPassw0rdXY',
        ]);

        $this->put(route('password.force.update'), [
            'password' => 'TempPassw0rdXY',
            'password_confirmation' => 'TempPassw0rdXY',
        ])->assertSessionHasErrors('password');
    }
}
