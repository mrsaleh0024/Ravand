<?php

namespace App\Console\Commands;

use App\Services\Bale\BaleBot;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Console\Command;

/**
 * Long-polling listener for the Bale bots (ADR-020).
 *
 * On local Windows/XAMPP there is no public URL for webhooks, so this
 * command pulls `getUpdates` in a loop. It prints the chat_id of every
 * incoming message — the owner uses it once to discover their own chat id,
 * and optionally keeps it running for future incoming flows.
 *
 * Usage:
 *   php artisan ravand:bale:poll --bot=code
 *   php artisan ravand:bale:poll --bot=admin --once
 */
class BalePollCommand extends Command
{
    protected $signature = 'ravand:bale:poll
        {--bot=code : Which bot to poll: code|admin}
        {--once : Fetch once instead of looping}
        {--timeout=25 : Long-polling timeout per request (seconds)}';

    protected $description = 'Poll Bale bot updates and print chat ids (setup helper / local listener)';

    public function handle(CacheRepository $cache): int
    {
        $which = $this->option('bot') === 'admin' ? 'admin' : 'code';
        $bot = new BaleBot($which);

        if (! $bot->configured()) {
            $this->error('BALE_'.strtoupper($which).'_BOT_TOKEN is not set in .env');

            return self::FAILURE;
        }

        $me = $bot->getMe();

        if ($me === null) {
            $this->error('Bale API is unreachable or the token is invalid.');

            return self::FAILURE;
        }

        $this->info("Listening to [{$which}] bot @{$me['username']}. Send /start to it in Bale.");
        $this->line('Every incoming message prints its chat_id — copy it into Security settings or .env.');
        $this->line('Press Ctrl+C to stop.');

        $offset = (int) $cache->get($this->offsetKey($which), 0);

        do {
            $updates = $bot->getUpdates($offset, (int) $this->option('timeout'));

            if ($updates === null) {
                if ($this->option('once')) {
                    $this->warn('No response from Bale (network error).');

                    return self::FAILURE;
                }

                sleep(2);

                continue;
            }

            foreach ($updates as $update) {
                $offset = max($offset, (int) ($update['update_id'] ?? 0));
                $message = $update['message'] ?? null;

                if (! is_array($message)) {
                    continue;
                }

                $chatId = $message['chat']['id'] ?? null;
                $from = $message['from']['username'] ?? ($message['from']['first_name'] ?? '?');
                $text = $message['text'] ?? '';

                $this->line("chat_id=<comment>{$chatId}</comment> from=<info>@{$from}</info> text={$text}");
            }

            if ($updates !== []) {
                $cache->forever($this->offsetKey($which), $offset + 1);
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }

    private function offsetKey(string $which): string
    {
        return "bale:poll:offset:{$which}";
    }
}
