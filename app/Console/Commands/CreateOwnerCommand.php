<?php

namespace App\Console\Commands;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\UserStatus;
use App\Domain\Tenancy\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Secure initial-owner setup (spec phase 01).
 *
 * - No public registration exists; this command is the entry point.
 * - Passwords are never stored in code/seeders and never echoed back.
 * - Credentials may be provided via CLI options or .env variables
 *   (RAVAND_OWNER_*) for local convenience; the interactive hidden
 *   prompt is the safest path (see ADR-018).
 *
 * Resolution order per field: CLI option > .env/config > interactive prompt.
 */
class CreateOwnerCommand extends Command
{
    protected $signature = 'ravand:create-owner
        {--name= : Owner display name}
        {--email= : Owner email (login identifier)}
        {--tenant-name= : Store name}
        {--slug= : Store slug (URL identifier, immutable later)}
        {--currency= : Default currency IRR|IRT}
        {--password= : Password — automation/env only, never commit}';

    protected $description = 'Create the first owner account and its tenant securely (no public registration)';

    public function handle(AuditLogger $audit): int
    {
        $name = $this->resolve('name', 'owner.name') ?? text(label: 'Owner display name', required: true);
        $email = $this->resolve('email', 'owner.email') ?? text(label: 'Owner email (login identifier)', required: true);
        $tenantName = $this->resolve('tenant-name', 'owner.tenant_name') ?? text(label: 'Store name', required: true);
        $currency = $this->resolve('currency', 'owner.currency') ?? 'IRT';
        $slugInput = $this->resolve('slug', 'owner.tenant_slug');

        $password = $this->resolve('password', 'owner.password');

        if ($password === null) {
            $password = password(label: 'Password', required: true, validate: fn (string $value) => $this->validatePasswordPair($value, null) === [] ? null : implode(' — ', $this->validatePasswordPair($value, null)));
            $confirmation = password(label: 'Confirm password', required: true);

            if ($password !== $confirmation) {
                $this->error('The password and its confirmation do not match.');

                return self::FAILURE;
            }
        }

        $errors = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'tenant_name' => $tenantName,
                'currency' => $currency,
                'password' => $password,
            ],
            [
                'name' => ['required', 'string', 'max:80'],
                'email' => ['required', 'string', 'email:filter', 'max:190', 'unique:users,email'],
                'tenant_name' => ['required', 'string', 'max:120'],
                'currency' => ['required', 'string', Rule::in(config('ravand.allowed_currencies'))],
                // Password::defaults() is only strict in production; the owner
                // setup must always enforce a baseline policy (no weak pass).
                'password' => $this->passwordRules(),
            ],
        )->errors();

        if ($errors->isNotEmpty()) {
            foreach ($errors->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $slug = $this->resolveUniqueSlug($slugInput ?? $tenantName);

        [$user, $tenant] = DB::transaction(function () use ($name, $email, $tenantName, $slug, $currency, $password): array {
            $user = User::query()->create([
                'name' => $name,
                'email' => Str::lower($email),
                'status' => UserStatus::Active->value,
                'email_verified_at' => now(),
                'password' => $password,
            ]);

            $tenant = Tenant::query()->create([
                'name' => $tenantName,
                'slug' => $slug,
                'status' => TenantStatus::Active->value,
                'default_currency' => $currency,
                'timezone' => config('ravand.default_timezone'),
                'locale' => config('ravand.default_locale'),
            ]);

            TenantUser::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            return [$user, $tenant];
        });

        $audit->log(
            action: 'owner.created',
            tenant: $tenant,
            actor: $user,
            entityType: 'user',
            entityId: $user->id,
        );

        $audit->log(
            action: 'member.added',
            tenant: $tenant,
            actor: $user,
            entityType: 'tenant_user',
            entityId: $user->id,
            metadata: ['role' => 'owner', 'user_id' => $user->id],
        );

        $this->info('Owner and store created successfully.');
        $this->table(
            ['Key', 'Value'],
            [
                ['Owner name', $user->name],
                ['Email', $user->email],
                ['Store', $tenant->name],
                ['Slug', $tenant->slug],
                ['Currency', $tenant->default_currency],
            ],
        );

        return self::SUCCESS;
    }

    /**
     * Resolve a value from the CLI option first, then the .env-backed config
     * key. Empty/whitespace strings count as "not provided".
     */
    private function resolve(string $option, string $configKey): ?string
    {
        $value = $this->option($option) ?? config("ravand.{$configKey}");

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * Baseline password policy, independent of the environment
     * (Password::defaults() is only strict in production).
     *
     * @return array<int, Password|string>
     */
    private function passwordRules(): array
    {
        return app()->isProduction()
            ? ['required', 'string', Password::defaults()]
            : ['required', 'string', Password::min(10)->letters()->numbers()];
    }

    /**
     * @param  array<int, string> validation errors for the password pair
     */
    private function validatePasswordPair(string $value, ?string $confirmation): array
    {
        $messages = Validator::make(
            ['password' => $value, 'password_confirmation' => $confirmation ?? $value],
            [...$this->passwordRules(), 'confirmed'],
        )->errors()->all();

        return $messages;
    }

    /** Build a URL-safe unique slug from any name (Persian included). */
    private function resolveUniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'tenant';

        $slug = Str::limit($base, 40, '');
        $candidate = $slug;
        $attempt = 1;

        while (Tenant::query()->where('slug', $candidate)->exists()) {
            $candidate = Str::limit($slug, 34, '').'-'.Str::lower(Str::random(4).(++$attempt));
        }

        return $candidate;
    }
}
