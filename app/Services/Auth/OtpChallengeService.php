<?php

namespace App\Services\Auth;

use App\Domain\Audit\AuditLogger;
use App\Models\User;
use App\Services\Bale\BaleBot;
use Illuminate\Support\Facades\Cache;

/**
 * Owner login OTP via Bale (ADR-020).
 *
 * The senior manager (owner) completes every login with a 6-digit code sent
 * by the `code` bot. Codes are only stored hashed, expire after 5 minutes,
 * accept 5 verification attempts and are re-sendable after a 60s cooldown.
 * Managers/operators never require OTP.
 */
final class OtpChallengeService
{
    private const CACHE_CODE = 'otp:login:code:%s';
    private const CACHE_ATTEMPTS = 'otp:login:attempts:%s';
    private const CACHE_COOLDOWN = 'otp:login:cooldown:%s';

    public function __construct(private readonly AuditLogger $audit) {}

    /** Whether the given user must pass the OTP step on login. */
    public function requiredFor(User $user): bool
    {
        if (! config('ravand.bale.owner_2fa')) {
            return false;
        }

        if (! $user->isOwner()) {
            return false;
        }

        return BaleBot::code()->configured() && filled($user->bale_chat_id);
    }

    /**
     * Start the challenge: generate, store and send the code, and put the
     * pending user id into the session (session id is rotated first).
     * Returns false when sending via Bale failed.
     */
    public function startChallenge(\Illuminate\Http\Request $request, User $user): bool
    {
        $code = (string) random_int(100000, 999999);
        $key = $user->getKey();

        Cache::put($this->codeKey($key), hash('sha256', $code), $this->ttl());
        Cache::forget($this->attemptsKey($key));

        $sent = BaleBot::code()->sendMessage(
            (string) $user->bale_chat_id,
            $this->otpMessage($code, $user),
        );

        if (! $sent) {
            Cache::forget($this->codeKey($key));
            $this->audit->log(
                action: 'auth.otp.send_failed',
                tenant: null,
                actor: $user,
                entityType: 'user',
                entityId: $key,
            );

            return false;
        }

        $request->session()->regenerate();
        $request->session()->put('otp.pending_user_id', $key);
        $request->session()->put('otp.remember', $request->boolean('remember'));

        Cache::put($this->cooldownKey($key), true, $this->resendCooldown());

        $this->audit->log(
            action: 'auth.otp.issued',
            tenant: null,
            actor: $user,
            entityType: 'user',
            entityId: $key,
            metadata: ['bot' => 'code', 'expires_in' => $this->ttl()],
        );

        return true;
    }

    /** Resend after the cooldown; returns false while cooling down or on send failure. */
    public function resend(\Illuminate\Http\Request $request): bool
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return false;
        }

        if (Cache::has($this->cooldownKey($user->getKey()))) {
            return false;
        }

        return $this->startChallenge($request, $user);
    }

    /**
     * Verify the typed code for the pending user. Returns the user on
     * success, null on any failure (wrong/expired code, too many attempts).
     */
    public function verify(\Illuminate\Http\Request $request, string $code): ?User
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return null;
        }

        $key = $user->getKey();
        $cacheCode = Cache::get($this->codeKey($key));

        if (! is_string($cacheCode) || $cacheCode === '') {
            return null;
        }

        if ((int) Cache::get($this->attemptsKey($key), 0) >= $this->maxAttempts()) {
            $this->forgetAll($key);

            return null;
        }

        if (! hash_equals($cacheCode, hash('sha256', $code))) {
            Cache::put(
                $this->attemptsKey($key),
                (int) Cache::get($this->attemptsKey($key), 0) + 1,
                $this->ttl(),
            );

            $this->audit->log(
                action: 'auth.otp.invalid',
                tenant: null,
                actor: $user,
                entityType: 'user',
                entityId: $key,
            );

            return null;
        }

        $this->forgetAll($key);

        return $user;
    }

    /** Seconds until a re-send is allowed (0 = immediately). */
    public function cooldownRemaining(\Illuminate\Http\Request $request): int
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return 0;
        }

        $expiresAt = Cache::get($this->cooldownKey($user->getKey()));

        if ($expiresAt instanceof \DateTimeInterface) {
            return max(0, now()->diffInSeconds($expiresAt, false));
        }

        return 0;
    }

    /** Clear any pending challenge state from the session. */
    public function abort(\Illuminate\Http\Request $request): void
    {
        $request->session()->forget(['otp.pending_user_id', 'otp.remember']);
    }

    private function pendingUser(\Illuminate\Http\Request $request): ?User
    {
        $id = $request->session()->get('otp.pending_user_id');

        if (! is_string($id) || $id === '') {
            return null;
        }

        $user = User::query()->find($id);

        if ($user === null || $user->status !== \App\Domain\Identity\UserStatus::Active) {
            return null;
        }

        return $user;
    }

    private function forgetAll(string $userId): void
    {
        Cache::forget($this->codeKey($userId));
        Cache::forget($this->attemptsKey($userId));
        Cache::forget($this->cooldownKey($userId));
    }

    private function otpMessage(string $code, User $user): string
    {
        $minutes = intdiv($this->ttl(), 60);

        return __('otp.sms', ['code' => $code, 'minutes' => $minutes, 'name' => $user->name]);
    }

    private function ttl(): int
    {
        return max(60, (int) config('ravand.bale.otp.ttl', 300));
    }

    private function maxAttempts(): int
    {
        return max(1, (int) config('ravand.bale.otp.attempts', 5));
    }

    private function resendCooldown(): int
    {
        return max(10, (int) config('ravand.bale.otp.resend_cooldown', 60));
    }

    private function codeKey(string $id): string
    {
        return sprintf(self::CACHE_CODE, $id);
    }

    private function attemptsKey(string $id): string
    {
        return sprintf(self::CACHE_ATTEMPTS, $id);
    }

    private function cooldownKey(string $id): string
    {
        return sprintf(self::CACHE_COOLDOWN, $id);
    }
}
