<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\OtpChallengeService;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;

/**
 * Owner second-factor challenge (ADR-020): verifies the 6-digit code the
 * owner received through the Bale `code` bot. The session is only
 * authenticated here — the credential step never completed the login.
 */
class OtpChallengeController extends Controller
{
    public function __construct(
        private readonly OtpChallengeService $otp,
        private readonly LoginRateLimiter $limiter,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('login');
        }

        return Inertia::render('auth/OtpChallenge', [
            'phone' => Phone::mask((string) $this->pendingUser($request)?->phone),
            'cooldown' => $this->otp->cooldownRemaining($request),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        if ($this->pendingUser($request) === null) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $user = $this->otp->verify($request, $validated['code']);

        if ($user === null) {
            $this->limiter->increment($request);

            throw \Illuminate\Validation\ValidationException::withMessages([
                'code' => [trans('otp.invalid')],
            ]);
        }

        $remember = (bool) $request->session()->pull('otp.remember', false);
        $this->otp->abort($request);

        Auth::guard('web')->login($user, $remember);

        $request->session()->regenerate();
        $this->limiter->clear($request);

        app(\App\Domain\Audit\AuditLogger::class)->log(
            action: 'auth.otp.verified',
            tenant: null,
            actor: $user,
            entityType: 'user',
            entityId: $user->getKey(),
        );

        return redirect()->intended(route('dashboard'));
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($this->pendingUser($request) === null) {
            return redirect()->route('login');
        }

        $sent = $this->otp->resend($request);

        if (! $sent) {
            return back()->withErrors([
                'code' => $this->otp->cooldownRemaining($request) > 0
                    ? trans('otp.cooldown', ['seconds' => $this->otp->cooldownRemaining($request)])
                    : trans('otp.send_failed'),
            ]);
        }

        return back()->with('status', trans('otp.resent'));
    }

    private function pendingUser(Request $request): ?\App\Models\User
    {
        $id = $request->session()->get('otp.pending_user_id');

        if (! is_string($id) || $id === '') {
            return null;
        }

        return \App\Models\User::query()->find($id);
    }
}
