<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use App\Services\Bale\BaleBot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Owner Bale settings (ADR-020).
 *
 * The owner links their Bale account by entering the numeric chat id of the
 * conversation with each bot (the `ravand:bale:poll` helper prints the id
 * for every incoming message). Only the owner's chat is ever written to.
 */
class BaleSettingsController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function update(Request $request): RedirectResponse
    {
        abort_unless(Gate::allows('panel.bale.manage'), 403);

        $validated = $request->validate([
            'bale_chat_id' => ['required', 'string', 'max:32', 'regex:/^-?\d{1,20}$/'],
        ]);

        $request->user()->forceFill([
            'bale_chat_id' => ltrim($validated['bale_chat_id'], '-'),
        ])->save();

        $this->audit->log(
            action: 'settings.bale.updated',
            tenant: null,
            actor: $request->user(),
            entityType: 'user',
            entityId: $request->user()->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('otp.bale_saved')]);

        return back();
    }

    /** Send a test message through one or both bots to the owner's chat. */
    public function test(Request $request): RedirectResponse
    {
        abort_unless(Gate::allows('panel.bale.manage'), 403);

        $user = $request->user();
        $chatId = (string) $user->bale_chat_id;

        if ($chatId === '') {
            return back()->withErrors(['bale_chat_id' => __('otp.bale_chat_required')]);
        }

        $which = $request->input('bot', 'code');
        $bots = $which === 'all' ? [BaleBot::code(), BaleBot::admin()] : [new BaleBot($which === 'admin' ? 'admin' : 'code')];

        $results = [];

        foreach ($bots as $bot) {
            $ok = $bot->configured()
                ? $bot->sendMessage($chatId, __('otp.bale_test_message', ['app' => config('app.name')]))
                : false;

            $results[$bot->which()] = $ok;
        }

        $this->audit->log(
            action: 'bale.test_sent',
            tenant: null,
            actor: $user,
            entityType: 'user',
            entityId: $user->id,
            metadata: ['results' => $results],
        );

        Inertia::flash('baleTest', $results);

        return back();
    }

    /** Status page data for the security screen. */
    public function status(Request $request): Response
    {
        abort_unless(Gate::allows('panel.bale.manage'), 403);

        return Inertia::render('settings/Security', [
            'bale' => [
                'chat_id' => $request->user()->bale_chat_id,
                'code_bot' => BaleBot::code()->configured(),
                'admin_bot' => BaleBot::admin()->configured(),
            ],
        ]);
    }
}
