<?php

namespace App\Services\Bale;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Minimal Bale bot HTTP client (ADR-020).
 *
 * Bale's bot API is Telegram-compatible: requests go to
 * `https://tapi.bale.ai/bot<TOKEN>/<method>` and responses always carry an
 * `ok` boolean. Only two bots exist:
 *
 *  - `code`  → sends the owner's login OTP codes.
 *  - `admin` → mirrors password-reset requests / member events to the owner.
 *
 * No end-user ever receives a bot message: both bots only ever write to the
 * owner's chat. Failures are logged, never thrown into the UI — an outage of
 * Bale must not break login or panel operations.
 */
final class BaleBot
{
    public function __construct(
        private readonly string $which = 'code',
    ) {}

    /** Named constructors keep call sites readable. */
    public static function code(): self
    {
        return new self('code');
    }

    public static function admin(): self
    {
        return new self('admin');
    }

    /** Whether this bot has a token configured (never logging the token). */
    public function configured(): bool
    {
        return is_string($this->token()) && $this->token() !== '';
    }

    /** Which of the two configured bots this instance wraps. */
    public function which(): string
    {
        return $this->which;
    }

    /**
     * Send a plain-text message. Returns true on `ok:true`, false otherwise.
     *
     * @param  array<string, mixed>  $extra  extra sendMessage parameters
     */
    public function sendMessage(string $chatId, string $text, array $extra = []): bool
    {
        $token = $this->token();

        if ($token === null || $chatId === '' || $chatId === null) {
            return false;
        }

        try {
            $response = Http::timeout(10)
                ->retry(1, 300)
                ->asJson()
                ->post($this->endpoint($token, 'sendMessage'), [
                    'chat_id' => $chatId,
                    'text' => $text,
                    ...$extra,
                ]);

            if ($response->json('ok') !== true) {
                Log::warning('bale.send_failed', [
                    'bot' => $this->which,
                    'error_code' => $response->json('error_code'),
                    'description' => $response->json('description'),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('bale.send_exception', [
                'bot' => $this->which,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Fetch pending updates (long-polling). Returns the raw `result` array
     * or null on failure; never throws.
     *
     * @param  int  $offset  update_id offset (pass last+1 for acknowledged)
     * @return array<int, array<string, mixed>>|null
     */
    public function getUpdates(int $offset = 0, int $timeout = 25): ?array
    {
        $token = $this->token();

        if ($token === null) {
            return null;
        }

        try {
            $response = Http::timeout($timeout + 10)
                ->asJson()
                ->post($this->endpoint($token, 'getUpdates'), [
                    'offset' => $offset,
                    'timeout' => $timeout,
                    'allowed_updates' => ['message'],
                ]);

            if ($response->json('ok') !== true) {
                return null;
            }

            return $response->json('result') ?? [];
        } catch (Throwable $e) {
            Log::warning('bale.get_updates_failed', [
                'bot' => $this->which,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** Resolve the bot username via getMe (used for setup diagnostics). */
    public function getMe(): ?array
    {
        $token = $this->token();

        if ($token === null) {
            return null;
        }

        try {
            $response = Http::timeout(10)->get($this->endpoint($token, 'getMe'));

            return $response->json('ok') === true ? $response->json('result') : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function token(): ?string
    {
        $value = config("ravand.bale.{$this->which}_bot_token");

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function endpoint(string $token, string $method): string
    {
        return rtrim(config('ravand.bale.api_base'), '/')."/{$token}/{$method}";
    }
}
