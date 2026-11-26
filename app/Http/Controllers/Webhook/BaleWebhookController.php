<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Bale\BaleBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Bale webhook endpoint (ADR-020) — for servers with a public URL.
 *
 * Local development uses `ravand:bale:poll` instead. The endpoint
 * authenticates via Bale's secret-token header (set with setWebhook), then
 * only logs updates. Nothing is ever sent to a user chat from here except a
 * /start reply that echoes the chat_id (setup convenience for the owner).
 */
class BaleWebhookController extends Controller
{
    public function __invoke(Request $request, string $bot): JsonResponse
    {
        $which = $bot === 'admin' ? 'admin' : 'code';

        $secret = config('ravand.bale.webhook_secret');
        $header = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (! is_string($secret) || $secret === '' || ! hash_equals($secret, (string) $header)) {
            abort(403);
        }

        $update = $request->json()->all();

        $message = $update['message'] ?? null;
        $chatId = $message['chat']['id'] ?? null;

        if (is_scalar($chatId)) {
            Log::info('bale.webhook.message', [
                'bot' => $which,
                'chat_id' => $chatId,
                'from' => $message['from']['username'] ?? null,
            ]);

            // Setup convenience: echo the chat id so the owner can copy it.
            if (($message['text'] ?? '') === '/start') {
                (new BaleBot($which))->sendMessage(
                    (string) $chatId,
                    __('otp.webhook_start', ['chat_id' => $chatId, 'bot' => $which]),
                );
            }
        }

        return response()->json(['ok' => true]);
    }
}
