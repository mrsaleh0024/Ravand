<?php

namespace Tests\Feature\Settings;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Security settings page (ADR-020): password change plus the owner's
 * Bale 2FA block. Passkeys and TOTP two-factor were removed.
 */
class SecurityTest extends TestCase
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

    /** Create a user and promote them to owner of a fresh tenant. */
    protected function makeOwner(array $extra = []): User
    {
        $user = User::factory()->create($extra);

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

    public function test_security_page_is_displayed_for_regular_user()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Security')
                ->where('bale.isOwner', false)
                ->where('bale.chatId', null)
            );
    }

    public function test_security_page_exposes_bale_status_for_owner()
    {
        // Explicitly unset bot tokens so the status flags are deterministic.
        config([
            'ravand.bale.code_bot_token' => null,
            'ravand.bale.admin_bot_token' => null,
        ]);

        $owner = $this->makeOwner(['bale_chat_id' => '123456789']);

        $this->actingAs($owner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Security')
                ->where('bale.isOwner', true)
                ->where('bale.chatId', '123456789')
                ->where('bale.codeBot', false)
                ->where('bale.adminBot', false)
            );
    }

    public function test_owner_can_save_bale_chat_id()
    {
        $owner = $this->makeOwner();

        $response = $this
            ->actingAs($owner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->from(route('security.edit'))
            ->put(route('settings.bale.update'), [
                'bale_chat_id' => '987654321',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('security.edit'));

        $this->assertSame('987654321', $owner->refresh()->bale_chat_id);
    }

    public function test_non_owner_cannot_save_bale_chat_id()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->from(route('security.edit'))
            ->put(route('settings.bale.update'), [
                'bale_chat_id' => '987654321',
            ]);

        $response->assertForbidden();
    }

    public function test_password_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('security.edit'));

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('security.edit'));
    }
}
