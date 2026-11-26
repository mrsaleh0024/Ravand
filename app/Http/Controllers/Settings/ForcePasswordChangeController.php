<?php

namespace App\Http\Controllers\Settings;

use App\Concerns\PasswordValidationRules;
use App\Domain\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mandatory password change after a temporary password was used (ADR-021).
 * The flag `must_change_password` is enforced server-side by the
 * EnsurePasswordChanged middleware — the panel stays locked until a new
 * password is set.
 */
class ForcePasswordChangeController extends Controller
{
    use PasswordValidationRules;

    public function __construct(private readonly AuditLogger $audit) {}

    public function show(Request $request): Response
    {
        return Inertia::render('settings/ForcePasswordChange', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => $this->passwordRules(),
        ]);

        $user = $request->user();
        assert($user instanceof User);

        // The temporary password must not be re-used as the new one.
        if (Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => [trans('otp.temp_password_reused')],
            ]);
        }

        $user->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        $this->audit->log(
            action: 'auth.password_changed',
            tenant: null,
            actor: $user,
            entityType: 'user',
            entityId: $user->getKey(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('otp.password_changed')]);

        return redirect()->intended(route('dashboard'));
    }
}
