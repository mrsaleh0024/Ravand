<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Bale\BaleBot;
use Illuminate\Console\Command;

/**
 * Sends a test message to the owner's Bale chat through one of the bots.
 * The chat id comes from --chat, the owner's stored value, or the
 * RAVAND_BALE_CHAT_ID env variable (in that order).
 */
class BaleTestCommand extends Command
{
    protected $signature = 'ravand:bale:test
        {--bot=code : Which bot sends the message: code|admin|all}
        {--chat= : Target chat id (defaults to owner chat)}';

    protected $description = 'Send a test message through a Bale bot to the owner chat';

    public function handle(): int
    {
        $chatId = $this->option('chat')
            ?? User::query()->whereNotNull('bale_chat_id')->value('bale_chat_id')
            ?? config('ravand.bale.chat_id');

        if (! is_string($chatId) || $chatId === '') {
            $this->error('No chat id found. Use --chat=ID or set the owner chat in Security settings.');

            return self::FAILURE;
        }

        $which = $this->option('bot');
        $bots = match ($which) {
            'admin' => [BaleBot::admin()],
            'all' => [BaleBot::code(), BaleBot::admin()],
            default => [BaleBot::code()],
        };

        $anySent = false;

        foreach ($bots as $bot) {
            if (! $bot->configured()) {
                $this->warn("[{$bot->which()}] bot token is not configured (BALE_".strtoupper($bot->which()).'_BOT_TOKEN).');

                continue;
            }

            $sent = $bot->sendMessage($chatId, __('otp.bale_test_message', ['app' => config('app.name')]));

            $sent
                ? $this->info("[{$bot->which()}] test message sent to chat {$chatId}.")
                : $this->error("[{$bot->which()}] sending failed — check the log.");

            $anySent = $anySent || $sent;
        }

        return $anySent ? self::SUCCESS : self::FAILURE;
    }
}
