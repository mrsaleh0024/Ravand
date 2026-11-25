<?php

namespace App\Models;

use App\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only audit trail. Rows must never be edited or deleted through the
 * application; retention/cleanup happens only via a dedicated, restricted
 * maintenance job (future phase).
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string|null $actor_user_id
 * @property string $action
 * @property string|null $entity_type
 * @property string|null $entity_id
 * @property Carbon|null $occurred_at
 * @property string|null $ip_address
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 */
class AuditLog extends Model
{
    use HasUlid;

    /** Append-only rows: there is no meaningful update timestamp. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'actor_user_id',
        'action',
        'entity_type',
        'entity_id',
        'occurred_at',
        'ip_address',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
