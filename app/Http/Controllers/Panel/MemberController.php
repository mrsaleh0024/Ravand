<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Audit\AuditLogger;
use App\Domain\Tenancy\TenantContext;
use App\Domain\Tenancy\TenantRole;
use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tenant member management (owner & manager).
 *
 * - Owner: creates manager/operator accounts, suspends, removes, and can set
 *   a temporary password for any member.
 * - Manager: view + create operator accounts only (ADR-022).
 *
 * New accounts always start with `must_change_password = true`, so the first
 * login forces a personal password. The temporary password is shown once in
 * the panel; the manager contacts the person by phone.
 */
class MemberController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $tenant = $this->context->tenant();

        $members = TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->with('user:id,name,phone,status,last_login_at,must_change_password,created_at')
            ->orderBy('created_at')
            ->get()
            ->map(fn (TenantUser $membership): array => [
                'id' => $membership->id,
                'user_id' => $membership->user_id,
                'name' => $membership->user?->name,
                'phone' => $membership->user?->phone,
                'status' => $membership->status->value,
                'role' => $membership->role->value,
                'must_change_password' => (bool) $membership->user?->must_change_password,
                'last_login_at' => $membership->user?->last_login_at?->toISOString(),
                'joined_at' => $membership->joined_at?->toISOString(),
                'is_self' => $membership->user_id === $request->user()->id,
            ]);

        return Inertia::render('panel/Members', [
            'members' => $members,
            'canManage' => $this->context->role() === TenantRole::Owner,
        ]);
    }

    /** Create a new manager (owner) or operator (owner/manager) account. */
    public function store(Request $request): RedirectResponse
    {
        $role = $request->validate([
            'role' => ['required', Rule::in([TenantRole::Manager->value, TenantRole::Operator->value])],
        ])['role'];

        // Only the owner may create managers.
        if ($role === TenantRole::Manager->value) {
            $this->authorizeAbility('panel.members.create-manager');
        } else {
            $this->authorizeAbility('panel.members.create-operator');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'max:20', Rule::unique(User::class, 'phone')],
            'temp_password' => ['nullable', 'string', Password::min(10)->letters()->numbers()],
        ]);

        $phone = Phone::normalize($validated['phone']);
        abort_if($phone === null, 422, __('validation.phone_iranian'));

        $tempPassword = $validated['temp_password'] ?? \Illuminate\Support\Str::password(14, symbols: false);

        $tenant = $this->context->tenant();

        [$user] = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $phone, $tempPassword, $role, $tenant, $request): array {
            $user = User::query()->create([
                'name' => $validated['name'],
                'phone' => $phone,
                'status' => \App\Domain\Identity\UserStatus::Active->value,
                'password' => $tempPassword,
                'must_change_password' => true,
            ]);

            TenantUser::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role' => $role,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            return [$user];
        });

        $this->audit->log(
            action: 'member.created',
            tenant: $tenant,
            actor: $request->user(),
            entityType: 'tenant_user',
            entityId: $user->id,
            metadata: ['role' => $role, 'phone' => Phone::mask($phone)],
        );

        // The temp password is flashed once; it is never stored in plaintext.
        Inertia::flash('tempPassword', ['user' => $user->name, 'phone' => $phone, 'password' => $tempPassword]);

        return back();
    }

    /** Suspend or reactivate a member (owner only, never self). */
    public function toggle(Request $request, TenantUser $member): RedirectResponse
    {
        $this->authorizeAbility('panel.members.manage');
        abort_unless($member->tenant_id === $this->context->tenant()->id, 403);
        abort_if($member->user_id === $request->user()->id, 422, __('otp.cannot_target_self'));
        abort_unless($member->role !== TenantRole::Owner, 403);

        $member->update([
            'status' => $member->status === \App\Domain\Tenancy\MembershipStatus::Active
                ? 'suspended'
                : 'active',
        ]);

        $this->audit->log(
            action: $member->status === \App\Domain\Tenancy\MembershipStatus::Active ? 'member.activated' : 'member.suspended',
            tenant: $this->context->tenant(),
            actor: $request->user(),
            entityType: 'tenant_user',
            entityId: $member->id,
            metadata: ['role' => $member->role->value],
        );

        return back();
    }

    /** Remove a membership (owner only, never self, never owner). */
    public function destroy(Request $request, TenantUser $member): RedirectResponse
    {
        $this->authorizeAbility('panel.members.manage');
        abort_unless($member->tenant_id === $this->context->tenant()->id, 403);
        abort_if($member->user_id === $request->user()->id, 422, __('otp.cannot_target_self'));
        abort_unless($member->role !== TenantRole::Owner, 403);

        $member->delete();

        $this->audit->log(
            action: 'member.removed',
            tenant: $this->context->tenant(),
            actor: $request->user(),
            entityType: 'tenant_user',
            entityId: $member->id,
            metadata: ['role' => $member->role->value, 'user_id' => $member->user_id],
        );

        return back();
    }

    /** Issue a temporary password for an existing member (owner only). */
    public function resetPassword(Request $request, TenantUser $member): RedirectResponse
    {
        $this->authorizeAbility('panel.members.reset-password');
        abort_unless($member->tenant_id === $this->context->tenant()->id, 403);
        abort_unless($member->role !== TenantRole::Owner, 403);

        $validated = $request->validate([
            'temp_password' => ['nullable', 'string', Password::min(10)->letters()->numbers()],
        ]);

        $tempPassword = $validated['temp_password'] ?? \Illuminate\Support\Str::password(14, symbols: false);

        /** @var User|null $target */
        $target = $member->user;
        abort_if($target === null, 404);

        $target->forceFill([
            'password' => $tempPassword,
            'must_change_password' => true,
        ])->save();

        $this->audit->log(
            action: 'member.password.temp_set',
            tenant: $this->context->tenant(),
            actor: $request->user(),
            entityType: 'user',
            entityId: $target->id,
            metadata: ['role' => $member->role->value],
        );

        Inertia::flash('tempPassword', ['user' => $target->name, 'phone' => $target->phone, 'password' => $tempPassword]);

        return back();
    }

    private function authorizeAbility(string $ability): void
    {
        /** @var \App\Models\User|null $actor */
        $actor = auth()->user();

        abort_unless($actor !== null && $actor->can($ability), 403);
    }
}
