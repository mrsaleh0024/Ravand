<?php

namespace App\Models;

use App\Concerns\HasUlid;
use App\Domain\Loyalty\LoyaltyTransactionType;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only loyalty points ledger row (phase 04, spec section 10).
 *
 * Rows are never updated or deleted — corrections are new opposite rows.
 * The customer balance is always derived (Σ signed `points`); it is never
 * stored on the customer.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $customer_id
 * @property string|null $order_id
 * @property string|null $idempotency_key
 * @property LoyaltyTransactionType $transaction_type
 * @property int $points
 * @property string|null $amount_basis
 * @property string|null $reason
 * @property string|null $reversal_of
 * @property string|null $created_by_user_id
 * @property Carbon|null $created_at
 */
#[Fillable(['tenant_id', 'customer_id', 'order_id', 'idempotency_key', 'transaction_type', 'points', 'amount_basis', 'reason', 'reversal_of', 'created_by_user_id'])]
class LoyaltyTransaction extends Model
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
            'transaction_type' => LoyaltyTransactionType::class,
            'points' => 'integer',
            'amount_basis' => 'string',
            'created_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** The order this earn/reverse row belongs to (null for adjustments). */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Panel user who recorded a manual adjustment. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** The earn row this reversal cancels out. */
    public function original(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of');
    }
}
