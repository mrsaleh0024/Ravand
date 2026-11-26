<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordResetRequest;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTenancy;
use Tests\TestCase;

/**
 * Bale bot integration (ADR-020): webhook endpoint, admin-bot mirroring.
 * All outbound Bale API calls are faked — no network in tests.
 */
class BaleIntegrationTest extends TestCase
{
    use CreatesTenancy, RefreshDatabase;

    protected function ownerWithChat(): User
    {
        $user = User::factory()->withBaleChat()->create();
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

    public function test_webhook_rejects_wrong_secret(): void
    {
        config(['ravand.bale.webhook_secret' => 'shhh']);

        $this->postJson(route('webhooks.bale', 'code'), [
            'update_id' => 1,
        ])->assertForbidden();
    }

    public function test_webhook_accepts_valid_update_and_replies_to_start(): void
    {
        config(['ravand.bale.webhook_secret' => 'shhh', 'ravand.bale.code_bot_token' => 'tok']);

        Http::fake(['tapi.bale.ai/*' => Http::response(['ok' => true])]);

        $this->postJson(
            route('webhooks.bale', 'code'),
            ['update_id' => 1, 'message' => ['chat' => ['id' => 123456], 'from' => ['username' => 'owner'], 'text' => '/start']],
            ['X-Telegram-Bot-Api-Secret-Token' => 'shhh'],
        )->assertOk();

        // One sendMessage echoing the chat id.
        $sent = collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), '/sendMessage'));
        $this->assertSame(1, $sent->count());
    }

    public function test_owner_bale_chat_can_be_saved_and_tested_via_panel(): void
    {
        $owner = $this->ownerWithChat();

        config(['ravand.bale.code_bot_token' => 'tok', 'ravand.bale.admin_bot_token' => 'tok2']);
        Http::fake(['tapi.bale.ai/*' => Http::response(['ok' => true])]);

        $response = $this->actingAs($owner)->put(route('settings.bale.update'), [
            'bale_chat_id' => '123456789',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('123456789', $owner->refresh()->bale_chat_id);

        $this->actingAs($owner)->post(route('settings.bale.test'), ['bot' => 'all'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_logs', ['action' => 'bale.test_sent']);
    }

    public function test_non_owner_cannot_update_bale_settings(): void
    {
        [$owner, $tenant] = $this->createOwner();
        $manager = User::factory()->create();
        $this->attachMembership($manager, $tenant, 'manager');

        $this->actingAs($manager)
            ->put(route('settings.bale.update'), ['bale_chat_id' => '999'])
            ->assertForbidden();
    }

    public function test_bale_outage_does_not_break_the_panel(): void
    {
        $owner = $this->ownerWithChat();

        // Admin bot configured but the network is down — reset request must
        // still be stored and the response must not be a server error.
        config(['ravand.bale.admin_bot_token' => 'tok']);
        Http::fake(fn () => throw new ConnectionException('offline'));

        $response = $this->post(route('password.request.store'), ['phone' => '09125550000']);

        $response->assertSessionHasNoErrors();
        $this->assertSame(0, PasswordResetRequest::query()->count(), 'Unknown phone must not create a request.');

        // Known phone: request is stored despite the Bale outage.
        $this->post(route('password.request.store'), ['phone' => $owner->phone]);
        $this->assertSame(1, PasswordResetRequest::query()->count());
    }
}
