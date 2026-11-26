<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Secure owner setup smoke checks (spec phase 01): no public registration,
 * no fixed password in code, deterministic tenant creation.
 */
class OwnerSetupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_owner_tenant_and_membership(): void
    {
        $this->artisan('ravand:create-owner', [
            '--name' => 'صاحب فروشگاه',
            '--phone' => '09121000000',
            '--tenant-name' => 'فروشگاه نمونه',
            '--slug' => 'sample-store',
            '--currency' => 'IRT',
            '--password' => 'S3cure-Passw0rd!phase01',
        ])->assertSuccessful();

        $user = User::query()->where('phone', '09121000000')->firstOrFail();
        $tenant = Tenant::query()->where('slug', 'sample-store')->firstOrFail();

        $this->assertSame('صاحب فروشگاه', $user->name);
        $this->assertSame('active', $user->status->value);
                $this->assertSame('Asia/Tehran', $tenant->timezone);
        $this->assertSame('IRT', $tenant->default_currency);

        $membership = TenantUser::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('owner', $membership->role->value);
        $this->assertSame('active', $membership->status->value);
        $this->assertSame($tenant->id, $membership->tenant_id);

        $this->assertDatabaseHas('audit_logs', ['action' => 'owner.created', 'tenant_id' => $tenant->id]);
    }

    public function test_command_reads_owner_credentials_from_config(): void
    {
        // Credentials provided via .env-backed config (ADR-018): the command
        // must run fully non-interactive.
        config([
            'ravand.owner' => [
                'name' => 'Env Owner',
                'phone' => '09121000001',
                'password' => 'S3cure-Passw0rd!phase01',
                'tenant_name' => 'Env Store',
                'tenant_slug' => 'env-store',
                'currency' => 'IRR',
            ],
        ]);

        $this->artisan('ravand:create-owner')->assertSuccessful();

        $user = User::query()->where('phone', '09121000001')->firstOrFail();
        $tenant = Tenant::query()->where('slug', 'env-store')->firstOrFail();

        $this->assertSame('Env Owner', $user->name);
        $this->assertSame('IRR', $tenant->default_currency);

        $membership = TenantUser::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('owner', $membership->role->value);
    }

    public function test_cli_options_take_priority_over_config(): void
    {
        config([
            'ravand.owner' => [
                'name' => 'Env Owner',
                'phone' => '09121000001',
                'password' => 'S3cure-Passw0rd!phase01',
                'tenant_name' => 'Env Store',
            ],
        ]);

        $this->artisan('ravand:create-owner', [
            '--phone' => '09121000002',
        ])->assertSuccessful();

        $this->assertNotNull(User::query()->where('phone', '09121000002')->first());
        $this->assertNull(User::query()->where('phone', '09121000001')->first());
    }

    public function test_command_rejects_duplicate_phone(): void
    {
        User::factory()->create(['phone' => '09121000000']);

        $this->artisan('ravand:create-owner', [
            '--name' => 'کاربر دوم',
            '--phone' => '09121000000',
            '--tenant-name' => 'فروشگاه دوم',
            '--currency' => 'IRT',
            '--password' => 'S3cure-Passw0rd!phase01',
        ])->assertExitCode(1);

        $this->assertSame(0, Tenant::query()->count());
    }

    public function test_command_rejects_weak_password(): void
    {
        $this->artisan('ravand:create-owner', [
            '--name' => 'کاربر سوم',
            '--phone' => '09121000003',
            '--tenant-name' => 'فروشگاه سوم',
            '--currency' => 'IRT',
            '--password' => 'weakpass',
        ])->assertExitCode(1);

        $this->assertNull(User::query()->where('phone', '09121000003')->first());
    }

    public function test_command_generates_unique_slug_for_duplicate_names(): void
    {
        $this->artisan('ravand:create-owner', [
            '--name' => 'مالک یک',
            '--phone' => '09121000004',
            '--tenant-name' => 'فروشگاه مشترک',
            '--currency' => 'IRT',
            '--password' => 'S3cure-Passw0rd!phase01',
        ])->assertSuccessful();

        $this->artisan('ravand:create-owner', [
            '--name' => 'مالک دو',
            '--phone' => '09121000005',
            '--tenant-name' => 'فروشگاه مشترک',
            '--currency' => 'IRT',
            '--password' => 'S3cure-Passw0rd!phase01',
        ])->assertSuccessful();

        $slugs = Tenant::query()->pluck('slug')->unique();

        $this->assertSame(2, $slugs->count(), 'Two tenants must never share one slug.');
    }

    public function test_member_add_command_attaches_existing_user(): void
    {
        $this->artisan('ravand:create-owner', [
            '--name' => 'مالک یک',
            '--phone' => '09121000004',
            '--tenant-name' => 'فروشگاه اول',
            '--slug' => 'store-one',
            '--currency' => 'IRT',
            '--password' => 'S3cure-Passw0rd!phase01',
        ])->assertSuccessful();

        $staff = User::factory()->create(['phone' => '09121000006']);

        $this->artisan('ravand:member:add', [
            'phone' => '09121000006',
            'tenant-slug' => 'store-one',
            '--role' => 'operator',
        ])->assertSuccessful();

        $membership = TenantUser::query()->where('user_id', $staff->id)->firstOrFail();
        $this->assertSame('operator', $membership->role->value);

        $this->assertDatabaseHas('audit_logs', ['action' => 'member.added']);

        // Duplicate attach must fail cleanly.
        $this->artisan('ravand:member:add', [
            'phone' => '09121000006',
            'tenant-slug' => 'store-one',
            '--role' => 'manager',
        ])->assertExitCode(1);
    }

    public function test_no_fixed_password_exists_in_seeder(): void
    {
        $this->artisan('db:seed')->assertSuccessful();

        $this->assertSame(0, User::query()->count(), 'DatabaseSeeder must not create accounts.');
        $this->assertSame(0, Tenant::query()->count());
    }

    protected function tearDown(): void
    {
        // Audit log rows block deletes at model level; RefreshDatabase recreates
        // schema per test, so nothing extra is needed here.
        parent::tearDown();
    }
}
