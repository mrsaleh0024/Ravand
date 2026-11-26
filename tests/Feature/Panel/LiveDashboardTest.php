<?php

namespace Tests\Feature\Panel;

use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenancy;
use Tests\TestCase;

/**
 * Live dashboard data endpoint (ADR-023): owner gets the full payload with
 * system health, manager gets events/stats, operator is denied.
 */
class LiveDashboardTest extends TestCase
{
    use CreatesTenancy, RefreshDatabase;

    public function test_owner_receives_full_live_payload(): void
    {
        [$owner, $tenant] = $this->createOwner();

        // Seed a couple of audited events so the feed is not empty.
        app(AuditLogger::class)->log(action: 'auth.login', tenant: null, actor: $owner);
        app(AuditLogger::class)->log(action: 'tenant.switched', tenant: $tenant, actor: $owner);

        $response = $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->getJson(route('panel.live'));

        $response->assertOk();

        $payload = $response->json();

        $this->assertArrayHasKey('events', $payload);
        $this->assertArrayHasKey('chart', $payload);
        $this->assertArrayHasKey('stats', $payload);
        $this->assertArrayHasKey('system', $payload, 'Owner must receive the system health block.');

        $this->assertSame(PHP_VERSION, $payload['system']['php_version']);
        $this->assertGreaterThanOrEqual(1, $payload['stats']['active_members']);
    }

    public function test_manager_receives_events_without_system_health(): void
    {
        [$owner, $tenant] = $this->createOwner();
        $manager = User::factory()->create();
        $this->attachMembership($manager, $tenant, 'manager');

        $response = $this->actingAs($manager)
            ->withCurrentTenant($manager, $tenant)
            ->getJson(route('panel.live'));

        $response->assertOk();
        $this->assertArrayNotHasKey('system', $response->json());
    }

    public function test_operator_is_denied(): void
    {
        [$owner, $tenant] = $this->createOwner();
        $operator = User::factory()->create();
        $this->attachMembership($operator, $tenant, 'operator');

        $this->actingAs($operator)
            ->withCurrentTenant($operator, $tenant)
            ->getJson(route('panel.live'))
            ->assertForbidden();
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->getJson(route('panel.live'))->assertUnauthorized();
    }
}
