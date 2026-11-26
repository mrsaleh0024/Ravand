<?php

namespace App\Http\Controllers\Settings;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Services\Bale\BaleBot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class SecurityController extends Controller
{
    use PasswordValidationRules;

    /**
     * Show the user's security settings page: password change plus the
     * owner's Bale 2FA block (ADR-020). Passkeys and TOTP were removed —
     * the owner's second factor is the Bale OTP.
     */
    public function edit(\Illuminate\Http\Request $request): Response
    {
        $user = $request->user();
        assert($user instanceof \App\Models\User);

        $props = [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            // Bale 2FA block (owner-only UI).
            'bale' => [
                'isOwner' => $user->isOwner(),
                'chatId' => $user->bale_chat_id,
                'codeBot' => BaleBot::code()->configured(),
                'adminBot' => BaleBot::admin()->configured(),
                'enabled' => config('ravand.bale.owner_2fa'),
            ],
        ];

        return Inertia::render('settings/Security', $props);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('otp.password_updated')]);

        return back();
    }
}
