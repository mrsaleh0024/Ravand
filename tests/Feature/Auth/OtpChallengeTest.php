<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Owner two-factor via Bale OTP (ADR-020).
 *
 * The owner logs in with phone+password, receives a 6-digit code through the
 * `code` bot and completes the login on the OTP screen. Managers/operators
 * never require the second factor. Bale API responses are faked.
 */
class OtpChallengeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        config([
            'ravand.bale.code_bot_token' => null,
            'ravand.bale.admin_bot_token' => null,
        ]);

        parent::tearDown();
    }

    protected function ownerWithBale(array $extra = []): User
    {
        config(['ravand.bale.code_bot_token' => 'test-token']);

        $user = User::factory()->withBaleChat()->create($extra);

        $tenant = Tenant::query()->create(['name' => 'فروشگاه', 'slug' => 'store-'.uniqid()]);
        TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $user;
    }

    protected function codeFromSentMessage(): string
    {
        $message = collect(Http::recorded())
            ->map(fn ($pair) => $pair[0]->data()['text'] ?? null)
            ->filter(fn ($text) => is_string($text))
            ->last()
            ?? '';

        preg_match('/\b(\d{6})\b/u', $message, $matches);

        return $matches[1] ?? '';
    }

    public function test_owner_login_is_redirected_to_otp_challenge(): void
    {
        Http::fake(['tapi.bale.ai/*' => Http::response(['ok' => true])]);

        $user = $this->ownerWithBale();

        $response = $this->post(route('login.store'), [
            'phone' => $user->phone,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('otp.challenge'));
        $this->assertGuest();

        // A visitor without a pending challenge is bounced back to the login.
        $this->flushSession();
        $this->get(route('otp.challenge'))->assertRedirect(route('login'));
    }

    public function test_owner_completes_login_with_the_sent_code(): void
    {
        Http::fake(['tapi.bale.ai/*' => Http::response(['ok' => true])]);

        $user = $this->ownerWithBale();

        $this->post(route('login.store'), [
            'phone' => $user->phone,
            'password' => 'password',
        ]);

        $code = $this->codeFromSentMessage();
        $this->assertSame(6, strlen($code), 'A 6-digit code must have been sent.');

        $this->get(route('otp.challenge'))->assertOk();

        $response = $this->post(route('otp.verify'), ['code' => $code]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.otp.issued', 'actor_user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.otp.verified', 'actor_user_id' => $user->id]);
    }

    public function test_wrong_code_is_rejected_and_limited_to_five_attempts(): void
    {
        Http::fake(['tapi.bale.ai/*' => Http::response(['ok' => true])]);

        $user = $this->ownerWithBale();

        $this->post(route('login.store'), [
            'phone' => $user->phone,
            'password' => 'password',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('otp.verify'), ['code' => '000000']);
        }

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.otp.invalid']);
    }

    public function test_resend_is_rate_limited_by_cooldown(): void
    {
        Http::fake(['tapi.bale.ai/*' => Http::response(['ok' => true])]);

        $user = $this->ownerWithBale();

        $this->post(route('login.store'), [
            'phone' => $user->phone,
            'password' => 'password',
        ]);

        $this->post(route('otp.resend'))->assertSessionHasErrors();

        // After the cooldown passes a re-send succeeds.
        Cache::forget("otp:login:cooldown:{$user->id}");
        $this->post(route('otp.resend'))->assertSessionHasNoErrors();
    }

    public function test_manager_does_not_require_otp(): void
    {
        config(['ravand.bale.code_bot_token' => 'test-token']);

        $manager = User::factory()->withBaleChat()->create();
        $tenant = Tenant::query()->create(['name' => 'فروشگاه', 'slug' => 'store-'.uniqid()]);
        TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $manager->id,
            'role' => 'manager',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->post(route('login.store'), [
            'phone' => $manager->phone,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($manager);
    }

    public function test_owner_without_bale_configuration_logs_in_directly_and_skip_is_audited(): void
    {
        config(['ravand.bale.code_bot_token' => 'test-token']);

        // Owner exists but has no chat id on file.
        $user = User::factory()->create();

        $tenant = Tenant::query()->create(['name' => 'فروشگاه', 'slug' => 'store-'.uniqid()]);
        TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->post(route('login.store'), [
            'phone' => $user->phone,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);

        $skip = AuditLog::query()->where('action', 'auth.otp.skipped')->first();
        $this->assertNotNull($skip);
        $this->assertSame('no_chat_id', $skip->metadata['reason']);
    }
}
