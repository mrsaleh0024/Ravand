<?php

namespace App\Models;

use App\Concerns\HasUlid;
use App\Domain\Identity\UserStatus;
use App\Domain\Tenancy\TenantRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property string $id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property UserStatus $status
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property Carbon|null $last_login_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property string|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'phone', 'status', 'password', 'email_verified_at', 'last_login_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUlid, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            /* @chisel-2fa */
            'two_factor_confirmed_at' => 'datetime',
            /* @end-chisel-2fa */
        ];
    }

    /** All tenant memberships of this user (any status).
     * @return BelongsToMany<Tenant, $this, TenantUser>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->withPivot('role', 'status', 'joined_at')
            ->withTimestamps()
            ->using(TenantUser::class);
    }

    /**
     * Active memberships of this user in active tenants — the only source
     * from which the current tenant may be resolved (never client input).
     *
     * @return Collection<int, TenantUser>
     */
    public function activeMemberships()
    {
        return TenantUser::query()
            ->where('user_id', $this->getKey())
            ->where('status', 'active')
            ->whereHas('tenant', fn ($query) => $query->where('status', 'active'))
            ->with('tenant')
            ->get();
    }

    /** Current role inside a tenant or null when there is no active membership. */
    public function roleIn(Tenant $tenant): ?TenantRole
    {
        $membership = TenantUser::query()
            ->where('user_id', $this->getKey())
            ->where('tenant_id', $tenant->getKey())
            ->where('status', 'active')
            ->first();

        return $membership?->role;
    }
}
