<?php

namespace App\Models;

use App\Concerns\HasUlid;
use App\Domain\Sales\PaymentMethod;
use App\Domain\Sales\PaymentRecordStatus;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A manually recorded payment (spec 5.5). The amount/method are never
 * edited after creation — corrections happen through status transitions
 * (refunded / voided) offered by OrderService; the order payment status
 * is derived from the set of payments.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $order_id
 * @property string|null $idempotency_key
 * @property string $amount
 * @property string $currency_code
 * @property PaymentMethod $payment_method
 * @property PaymentRecordStatus $status
 * @property Carbon|null $paid_at
 * @property string|null $reference_code
 * @property string|null $note
 * @property string|null $created_by_user_id
 */
#[Fillable(['tenant_id', 'order_id', 'idempotency_key', 'amount', 'currency_code', 'payment_method', 'status', 'paid_at', 'reference_code', 'note', 'created_by_user_id'])]
class Payment extends Model
{
    use HasUlid;

    /** Every query on this model MUST stay inside one tenant (spec 2.2). */
    protected static function booted(): void
    {
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
            'amount' => 'string',
            'payment_method' => PaymentMethod::class,
            'status' => PaymentRecordStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
