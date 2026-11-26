<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Services\Bale\BaleBot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesTenancy;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use CreatesTenancy, RefreshDatabase;

    public function test_security_page_is_displayed_with_bale_block_for_owner()
    {
        [$owner] = $this->createOwner();

        $this->actingAs($owner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Security')
                ->where('bale.isOwner', true)
                ->where('bale.chatId', null)
                ->where('bale.enabled', config('ravand.bale.owner_2fa')),
            );
    }

    public function test_security_page_shows_bale_block_as_non_owner_for_regular_user()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Security')
                ->where('bale.isOwner', false),
            );
    }

    public function test_owner_can_link_bale_chat_id()
    {
        config([
            'ravand.bale.code_bot_token' => 'test-code-token',
            'ravand.bale.admin_bot_token' => 'test-admin-token',
        ]);

        [$owner] = $this->createOwner();

        $this->actingAs($owner)
            ->from(route('security.edit'))
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('settings.bale.update'), ['bale_chat_id' => '987654321'])
            ->assertRedirect(route('security.edit'))
            ->assertSessionHasNoErrors();

        $this->assertSame('987654321', $owner->refresh()->bale_chat_id);
    }

    public function test_non_owner_cannot_link_bale_chat_id()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('settings.bale.update'), ['bale_chat_id' => '987654321'])
            ->assertForbidden();

        $this->assertNull($user->refresh()->bale_chat_id);
    }

    public function test_bale_test_message_is_sent_to_linked_chat()
    {
        config([
            'ravand.bale.code_bot_token' => 'test-code-token',
            'ravand.bale.api_base' => 'https://tapi.bale.ai/bot',
        ]);

        Http::fake();

        [$owner] = $this->createOwner();
        $owner->forceFill(['bale_chat_id' => '123456789'])->save();

        $this->actingAs($owner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('settings.bale.test'), ['bot' => 'code'])
            ->assertRedirect();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'tapi.bale.ai')
                && str_contains($request->url(), 'test-code-token')
                && $request['chat_id'] === '123456789';
        });
    }

    public function test_password_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('security.edit'))
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('security.edit'));

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('security.edit'))
            ->withSession(['auth.password_confirmed_at' => time()])
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
