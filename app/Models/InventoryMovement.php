<?php

namespace App\Models;

use App\Concerns\HasUlid;
use App\Domain\Sales\MovementType;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only inventory ledger row (spec 5.4). Rows are never updated or
 * deleted — corrections are new opposite movements.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $product_variant_id
 * @property string|null $idempotency_key
 * @property MovementType $movement_type
 * @property int $quantity_delta
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property string|null $reason
 * @property Carbon $occurred_at
 * @property string|null $created_by_user_id
 * @property Carbon|null $created_at
 */
#[Fillable(['tenant_id', 'product_variant_id', 'idempotency_key', 'movement_type', 'quantity_delta', 'reference_type', 'reference_id', 'reason', 'occurred_at', 'created_by_user_id'])]
class InventoryMovement extends Model
{
    use HasUlid;

    public const UPDATED_AT = null; // append-only ledger: created_at only

    /** Every query on this model MUST stay inside one tenant (spec 2.2). */
    protected static function booted(): void
    {
        // Append-only ledger: corrections are new opposite rows, never edits.
        static::updating(fn () => false);
        static::deleting(fn () => false);

        static::addGlobalScope('tenant', function ($query): void {
            $context = app(TenantContext::class);

            if ($context->has()) {
                $query->where($query->getModel()->getTable().'.tenant_id', $context->tenant()->id);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'movement_type' => MovementType::class,
            'quantity_delta' => 'integer',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
