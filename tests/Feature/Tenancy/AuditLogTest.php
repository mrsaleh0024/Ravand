<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Audit\AuditLogger;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenancy;
use Tests\TestCase;

/**
 * Audit log smoke checks (spec section 8.6, phase 01): login success/failure,
 * tenant settings changes and role/membership changes are recorded; rows are
 * append-only.
 */
class AuditLogTest extends TestCase
{
    use CreatesTenancy, RefreshDatabase;

    public function test_failed_login_is_audited_without_credentials(): void
    {
        $user = User::factory()->create();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $row = AuditLog::query()->where('action', 'auth.login_failed')->first();

        $this->assertNotNull($row);
        $this->assertSame($user->email, $row->metadata['attempted_identifier']);
        $this->assertStringNotContainsString('wrong-password', json_encode($row->toArray(), JSON_THROW_ON_ERROR));
    }

    public function test_successful_login_is_audited_and_updates_last_login(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه الف');

        $this->post(route('login'), [
            'email' => $owner->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();

        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'actor_user_id' => $owner->id]);
        $this->assertNotNull($owner->refresh()->last_login_at);
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه الف');
        $owner->update(['status' => 'suspended']);

        $this->post(route('login'), [
            'email' => $owner->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_settings_update_writes_changed_keys_only(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه الف');

        $this->actingAs($owner);
        $this->withCurrentTenant($owner, $tenant);

        $this->put(route('tenant.settings.update'), [
            'name' => 'نام تازه',
            'default_currency' => 'IRT', // unchanged
            'timezone' => 'Asia/Tehran', // unchanged
            'locale' => 'fa-IR', // unchanged
            'calendar' => 'jalali', // unchanged
        ])->assertRedirect();

        $row = AuditLog::query()->where('action', 'tenant.settings.updated')->first();

        $this->assertNotNull($row);
        $this->assertSame(['name'], $row->metadata['changed']);
        $this->assertSame($tenant->id, $row->tenant_id);
    }

    public function test_member_addition_by_command_is_audited(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه الف');
        $operator = User::factory()->create();

        $this->artisan('ravand:member:add', [
            'email' => $operator->email,
            'tenant-slug' => $tenant->slug,
            '--role' => 'operator',
        ])->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'member.added',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_audit_rows_are_append_only(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه الف');

        // Seed one audit row for the tamper attempt.
        $row = app(AuditLogger::class)->log(
            action: 'tenant.settings.updated',
            tenant: $tenant,
            actor: $owner,
        );

        $originalAction = $row->action;
        $row->action = 'tampered.action';
        $row->save();

        $row->refresh();
        $this->assertSame($originalAction, $row->action, 'Audit rows must not be editable.');
    }
}
