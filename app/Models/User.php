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
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $phone
 * @property UserStatus $status
 * @property string $password
 * @property bool $must_change_password
 * @property string|null $bale_chat_id
 * @property Carbon|null $last_login_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'phone', 'status', 'password', 'must_change_password', 'bale_chat_id', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUlid;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'status' => UserStatus::class,
        ];
    }

    /** Whether this user holds an active owner membership anywhere. */
    public function isOwner(): bool
    {
        return TenantUser::query()
            ->where('user_id', $this->getKey())
            ->where('role', TenantRole::Owner->value)
            ->where('status', 'active')
            ->exists();
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
