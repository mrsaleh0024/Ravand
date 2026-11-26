<?php

namespace App\Models;

use App\Concerns\HasUlid;
use App\Domain\Sales\FulfillmentMethod;
use App\Domain\Sales\OrderStatus;
use App\Domain\Sales\SalesChannel;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A manually recorded sale (spec 5.5).
 *
 * Amount columns are DECIMAL(20,0) strings — integer money, never floats.
 * currency_code is a snapshot of the tenant currency at placement; the
 * items snapshot everything about the purchased variants.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $customer_id
 * @property string|null $idempotency_key
 * @property string $order_number
 * @property SalesChannel $sales_channel
 * @property FulfillmentMethod $fulfillment_method
 * @property OrderStatus $status
 * @property Carbon|null $returned_at
 * @property string|null $return_note
 * @property Carbon $placed_at
 * @property Carbon|null $completed_at
 * @property string $subtotal_amount
 * @property string $discount_amount
 * @property string $shipping_amount
 * @property string $total_amount
 * @property string $currency_code
 * @property string|null $customer_note
 * @property string|null $internal_note
 * @property array<string, mixed>|null $shipping_address_snapshot
 * @property string|null $created_by_user_id
 */
#[Fillable(['tenant_id', 'customer_id', 'idempotency_key', 'order_number', 'sales_channel', 'fulfillment_method', 'status', 'returned_at', 'return_note', 'placed_at', 'completed_at', 'subtotal_amount', 'discount_amount', 'shipping_amount', 'total_amount', 'currency_code', 'customer_note', 'internal_note', 'shipping_address_snapshot', 'created_by_user_id'])]
class Order extends Model
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
            'sales_channel' => SalesChannel::class,
            'fulfillment_method' => FulfillmentMethod::class,
            'status' => OrderStatus::class,
            'returned_at' => 'datetime',
            'placed_at' => 'datetime',
            'completed_at' => 'datetime',
            'subtotal_amount' => 'string',
            'discount_amount' => 'string',
            'shipping_amount' => 'string',
            'total_amount' => 'string',
            'shipping_address_snapshot' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
