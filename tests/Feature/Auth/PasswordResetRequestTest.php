<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordResetRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTenancy;
use Tests\TestCase;

/**
 * Password reset request flow (ADR-021): request from the login screen,
 * panel approval with a temporary password, forced password change.
 */
class PasswordResetRequestTest extends TestCase
{
    use CreatesTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ravand.bale.admin_bot_token' => 'test-token']);
        Http::fake(['tapi.bale.ai/*' => Http::response(['ok' => true])]);
    }

    protected function tearDown(): void
    {
        config(['ravand.bale.admin_bot_token' => null]);

        parent::tearDown();
    }

    public function test_request_is_created_and_mirrored_to_owner_bale(): void
    {
        [$owner, $tenant] = $this->createOwner();
        $owner->update(['bale_chat_id' => '999888777']);

        $manager = User::factory()->create();
        $this->attachMembership($manager, $tenant, 'manager');

        $response = $this->post(route('password.request.store'), [
            'phone' => $manager->phone,
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('password_reset_requests', [
            'phone' => $manager->phone,
            'status' => 'pending',
        ]);

        // One message to the owner through the admin bot.
        $sent = collect(Http::recorded())->filter(
            fn ($pair) => str_contains((string) $pair[0]->url(), 'test-token'),
        );
        $this->assertSame(1, $sent->count());
    }

    public function test_unknown_phone_gets_the_same_response_without_creating_a_request(): void
    {
        $response = $this->post(route('password.request.store'), [
            'phone' => '09129999999',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(0, PasswordResetRequest::query()->count());
    }

    public function test_duplicate_pending_request_is_not_created_twice(): void
    {
        [$owner, $tenant] = $this->createOwner();
        $operator = User::factory()->create();
        $this->attachMembership($operator, $tenant, 'operator');

        foreach ([1, 2] as $attempt) {
            $this->post(route('password.request.store'), ['phone' => $operator->phone]);
        }

        $this->assertSame(1, PasswordResetRequest::query()->count());
    }

    public function test_owner_approves_request_and_temp_password_forces_change(): void
    {
        [$owner, $tenant] = $this->createOwner();
        $operator = User::factory()->create();
        $this->attachMembership($operator, $tenant, 'operator');

        $this->post(route('password.request.store'), ['phone' => $operator->phone]);

        $request = PasswordResetRequest::query()->where('phone', $operator->phone)->firstOrFail();

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('panel.reset-requests.approve', $request))
            ->assertRedirect();

        $this->assertDatabaseHas('password_reset_requests', [
            'id' => $request->id,
            'status' => 'approved',
            'handled_by' => $owner->id,
        ]);

        $operator->refresh();
        $this->assertTrue($operator->must_change_password);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.reset_approved']);

        // Login with the temp password lands on the force-change screen...
        $this->post(route('logout'));
        $this->assertGuest();
    }

    public function test_manager_cannot_approve_an_owner_request(): void
    {
        [$owner, $tenant] = $this->createOwner();
        $manager = User::factory()->create();
        $this->attachMembership($manager, $tenant, 'manager');

        $this->post(route('password.request.store'), ['phone' => $owner->phone]);
        $request = PasswordResetRequest::query()->where('phone', $owner->phone)->firstOrFail();

        $this->actingAs($manager)
            ->withCurrentTenant($manager, $tenant)
            ->post(route('panel.reset-requests.approve', $request))
            ->assertForbidden();
    }

    public function test_operator_cannot_see_reset_requests(): void
    {
        [$owner, $tenant] = $this->createOwner();
        $operator = User::factory()->create();
        $this->attachMembership($operator, $tenant, 'operator');

        $this->actingAs($operator)
            ->withCurrentTenant($operator, $tenant)
            ->get(route('panel.reset-requests.index'))
            ->assertForbidden();
    }
}
