<?php

namespace App\Auth\Pipeline;

use App\Domain\Identity\UserStatus;
use App\Services\Auth\OtpChallengeService;
use App\Support\Phone;
use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;

/**
 * Phone-based credential check + owner OTP branch (ADR-019, ADR-020).
 *
 * Replaces Fortify's AttemptToAuthenticate in the custom login pipeline
 * (config('fortify.pipelines.login')). When the resolved user is an owner
 * with Bale 2FA configured, the session is NOT authenticated yet — instead
 * an OTP challenge is started and the pipeline short-circuits to the OTP
 * screen. Everyone else logs in directly.
 */
class AttemptPhoneAuthentication
{
    public function __construct(
        private readonly StatefulGuard $guard,
        private readonly LoginRateLimiter $limiter,
        private readonly OtpChallengeService $otp,
    ) {}

    public function handle($request, $next)
    {
        $user = (new Timebox)->call(function ($timebox) use ($request) {
            return tap($this->resolveUser($request), function ($user) use ($request, $timebox) {
                if ($user === null) {
                    event(new Failed(
                        $this->guard?->name ?? config('fortify.guard'),
                        null,
                        [
                            Fortify::username() => $request->{Fortify::username()},
                            'password' => $request->password,
                        ],
                    ));

                    $this->limiter->increment($request);

                    throw ValidationException::withMessages([
                        Fortify::username() => [trans('auth.failed')],
                    ]);
                }

                $timebox->returnEarly();
            });
        }, config('auth.timebox_duration', 200000));

        if ($this->otp->requiredFor($user)) {
            if ($this->otp->startChallenge($request, $user)) {
                return redirect()->route('otp.challenge');
            }

            // Bale delivery failed — the user must retry from the login form
            // instead of getting stuck on an OTP screen with no way forward.
            $this->otp->abort($request);

            throw ValidationException::withMessages([
                Fortify::username() => [trans('otp.send_failed')],
            ]);
        }

        if ($user->isOwner()) {
            // OTP is skipped only when it is not configured at all — every
            // skip reason is audited so the security gap stays visible.
            $skipReason = match (true) {
                config('ravand.bale.owner_2fa') !== true => 'disabled',
                ! $this->baleCodeBot()->configured() => 'bot_not_configured',
                $user->bale_chat_id === null || $user->bale_chat_id === '' => 'no_chat_id',
                default => null,
            };

            if ($skipReason !== null) {
                app(\App\Domain\Audit\AuditLogger::class)->log(
                    action: 'auth.otp.skipped',
                    tenant: null,
                    actor: $user,
                    entityType: 'user',
                    entityId: $user->getKey(),
                    metadata: ['reason' => $skipReason],
                );
            }
        }

        $this->guard->login($user, $request->boolean('remember'));

        return $next($request);
    }

    private function baleCodeBot(): \App\Services\Bale\BaleBot
    {
        return \App\Services\Bale\BaleBot::code();
    }

    /** Credential resolution: phone lookup + active status + hash check. */
    private function resolveUser($request): ?\App\Models\User
    {
        $phone = Phone::normalize($request->input(Fortify::username()));
        $password = (string) $request->input('password');

        if ($phone === null || $password === '') {
            return null;
        }

        $user = \App\Models\User::query()->where('phone', $phone)->first();

        if ($user === null || $user->status !== UserStatus::Active) {
            return null;
        }

        return Hash::check($password, $user->password) ? $user : null;
    }
}
