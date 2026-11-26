<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\PasswordResetRequest;
use App\Models\User;
use App\Services\Bale\BaleBot;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Password reset requests (ADR-021).
 *
 * There are no emailed reset links. A manager/operator who forgot their
 * password files a request with their mobile number. The request is stored
 * for the panel and mirrored to the owner's Bale admin bot. The response is
 * always identical whether or not the number is known — no user enumeration.
 */
class ForgotPasswordRequestController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function show(Request $request): Response
    {
        return Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $phone = Phone::normalize($validated['phone']);

        if ($phone !== null) {
            $user = User::query()->where('phone', $phone)->first();

            $open = PasswordResetRequest::query()
                ->where('phone', $phone)
                ->where('status', PasswordResetRequest::STATUS_PENDING)
                ->exists();

            if ($user !== null && ! $open) {
                /** @var PasswordResetRequest $resetRequest */
                $resetRequest = PasswordResetRequest::query()->create([
                    'user_id' => $user->id,
                    'phone' => $phone,
                    'status' => PasswordResetRequest::STATUS_PENDING,
                    'requested_ip' => $request->ip(),
                ]);

                $this->audit->log(
                    action: 'auth.reset_requested',
                    tenant: null,
                    actor: null,
                    entityType: 'password_reset_request',
                    entityId: $resetRequest->id,
                    metadata: ['phone' => Phone::mask($phone)],
                );

                $this->notifyOwnerViaBale($user, $phone);
            }
        }

        // Identical response regardless of whether the number exists.
        return back()->with('status', trans('otp.reset_request_received'));
    }

    /** Mirror the request to the owner through the admin bot (best effort). */
    private function notifyOwnerViaBale(User $requester, string $phone): void
    {
        try {
            $bot = BaleBot::admin();

            if (! $bot->configured()) {
                return;
            }

            $ownerChatId = User::query()
                ->whereHas('tenants', fn ($q) => $q
                    ->where('tenant_user.role', 'owner')
                    ->where('tenant_user.status', 'active'))
                ->whereNotNull('bale_chat_id')
                ->value('bale_chat_id');

            if (! filled($ownerChatId)) {
                return;
            }

            $bot->sendMessage(
                (string) $ownerChatId,
                __('otp.reset_request_bot', [
                    'name' => $requester->name,
                    'phone' => $phone,
                ]),
            );
        } catch (Throwable $e) {
            // Mirroring is best effort: an outage must never break the flow.
            Log::warning('bale.reset_mirror_failed', ['exception' => $e->getMessage()]);
        }
    }
}
