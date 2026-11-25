<?php

namespace App\Models;

use App\Concerns\HasUlid;
use App\Domain\Tenancy\MembershipStatus;
use App\Domain\Tenancy\TenantRole;
use Database\Factories\TenantUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Pivot record: membership of a user inside one tenant with a role.
 *
 * Unique constraint (tenant_id, user_id) is enforced at the database level.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $user_id
 * @property TenantRole $role
 * @property MembershipStatus $status
 * @property Carbon|null $joined_at
 */
class TenantUser extends Pivot
{
    /** @use HasFactory<TenantUserFactory> */
    use HasFactory, HasUlid;

    protected $table = 'tenant_user';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'role',
        'status',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => TenantRole::class,
            'status' => MembershipStatus::class,
            'joined_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
