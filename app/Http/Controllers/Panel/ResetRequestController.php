<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\PasswordResetRequest;
use App\Models\TenantUser;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handling of password reset requests (ADR-021).
 *
 * Requests are filed from the login screen and mirrored to the owner's Bale
 * admin bot. In the panel, the owner handles every request; a manager may
 * handle requests for operators only. Approval issues a temporary password
 * (shown once in the panel) and forces the requester to set a new password
 * on their next login.
 */
class ResetRequestController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): Response
    {
        $requests = PasswordResetRequest::query()
            ->with(['user:id,name,phone', 'handler:id,name'])
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (PasswordResetRequest $request): array => [
                'id' => $request->id,
                'phone' => $request->phone,
                'status' => $request->status,
                'requested_at' => $request->created_at?->toISOString(),
                'handled_at' => $request->handled_at?->toISOString(),
                'requested_by' => $request->user?->only(['id', 'name']),
                'handled_by' => $request->handler?->name,
            ]);

        return Inertia::render('panel/ResetRequests', [
            'requests' => $requests,
        ]);
    }

    /** Approve a pending request by issuing a temporary password. */
    public function approve(Request $request, PasswordResetRequest $resetRequest): RedirectResponse
    {
        abort_unless(auth()->user()?->can('panel.reset-requests.manage'), 403);
        abort_unless($resetRequest->status === PasswordResetRequest::STATUS_PENDING, 422, __('otp.request_already_handled'));

        $target = $resetRequest->user;
        abort_if($target === null, 404);

        // Role hierarchy: a manager may never reset an owner's password.
        $targetRole = $this->highestRole($target);
        abort_if($targetRole === 'owner' && ! $request->user()->isOwner(), 403);

        $validated = $request->validate([
            'temp_password' => ['nullable', 'string', Password::min(10)->letters()->numbers()],
        ]);

        $tempPassword = $validated['temp_password'] ?? \Illuminate\Support\Str::password(14, symbols: false);

        \Illuminate\Support\Facades\DB::transaction(function () use ($resetRequest, $target, $tempPassword, $request): void {
            $target->forceFill([
                'password' => $tempPassword,
                'must_change_password' => true,
            ])->save();

            $resetRequest->update([
                'status' => PasswordResetRequest::STATUS_APPROVED,
                'handled_by' => $request->user()->id,
                'handled_at' => now(),
            ]);
        });

        $this->audit->log(
            action: 'auth.reset_approved',
            tenant: null,
            actor: $request->user(),
            entityType: 'password_reset_request',
            entityId: $resetRequest->id,
            metadata: ['user_id' => $target->id, 'phone' => Phone::mask($target->phone)],
        );

        Inertia::flash('tempPassword', ['user' => $target->name, 'phone' => $target->phone, 'password' => $tempPassword]);

        return back();
    }

    /** Reject a pending request. */
    public function reject(Request $request, PasswordResetRequest $resetRequest): RedirectResponse
    {
        abort_unless(auth()->user()?->can('panel.reset-requests.manage'), 403);
        abort_unless($resetRequest->status === PasswordResetRequest::STATUS_PENDING, 422, __('otp.request_already_handled'));

        $resetRequest->update([
            'status' => PasswordResetRequest::STATUS_REJECTED,
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ]);

        $this->audit->log(
            action: 'auth.reset_rejected',
            tenant: null,
            actor: $request->user(),
            entityType: 'password_reset_request',
            entityId: $resetRequest->id,
            metadata: ['phone' => Phone::mask($resetRequest->phone)],
        );

        return back();
    }

    /** Highest role the target user holds (owner > manager > operator). */
    private function highestRole(User $user): ?string
    {
        $roles = TenantUser::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->pluck('role')
            ->map(fn ($role) => $role->value)
            ->all();

        return match (true) {
            in_array('owner', $roles, true) => 'owner',
            in_array('manager', $roles, true) => 'manager',
            in_array('operator', $roles, true) => 'operator',
            default => null,
        };
    }
}
