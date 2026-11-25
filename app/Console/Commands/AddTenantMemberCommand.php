<?php

namespace App\Console\Commands;

use App\Domain\Audit\AuditLogger;
use App\Domain\Tenancy\TenantRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Adds an active membership for an existing user to a tenant. In the MVP
 * this replaces the (not yet built) in-panel invitation flow; it is meant to
 * be run by the owner on a trusted machine.
 */
class AddTenantMemberCommand extends Command
{
    protected $signature = 'ravand:member:add
        {email : Existing user email}
        {tenant-slug : Tenant slug}
        {--role=operator : Role: owner|manager|operator}';

    protected $description = 'Attach an existing user to a tenant with a role';

    public function handle(AuditLogger $audit): int
    {
        $email = Str::lower(trim($this->argument('email')));
        $tenantSlug = trim($this->argument('tenant-slug'));
        $role = $this->option('role');

        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $this->error('کاربری با این ایمیل یافت نشد.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()->where('slug', $tenantSlug)->first();
        if ($tenant === null) {
            $this->error('فروشگاهی با این اسلاگ یافت نشد.');

            return self::FAILURE;
        }

        $errors = Validator::make(['role' => $role], [
            'role' => ['required', 'string', Rule::in(array_column(TenantRole::cases(), 'value'))],
        ])->errors();

        if ($errors->isNotEmpty()) {
            foreach ($errors->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $existing = TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing !== null) {
            $this->error('این کاربر از قبل عضو این فروشگاه است.');

            return self::FAILURE;
        }

        $membership = TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $audit->log(
            action: 'member.added',
            tenant: $tenant,
            actor: $user,
            entityType: 'tenant_user',
            entityId: $membership->id,
            metadata: ['role' => $role, 'user_id' => $user->id],
        );

        $this->info("عضویت ثبت شد: {$user->email} → {$tenant->slug} ({$role})");

        return self::SUCCESS;
    }
}
