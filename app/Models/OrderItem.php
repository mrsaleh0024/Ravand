<?php

namespace App\Models;

use App\Concerns\HasUlid;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ordered line with everything snapshotted at purchase time
 * (spec 5.5): product/variant names, attributes, sku, prices. Later
 * catalog edits never rewrite this row.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $order_id
 * @property string|null $product_id
 * @property string|null $product_variant_id
 * @property string $product_name_snapshot
 * @property string|null $variant_name_snapshot
 * @property array<string, mixed>|null $variant_attributes_snapshot
 * @property string|null $sku_snapshot
 * @property int $quantity
 * @property string $unit_price
 * @property string $discount_amount
 * @property string $line_total
 * @property string|null $cost_price_snapshot
 */
#[Fillable(['tenant_id', 'order_id', 'product_id', 'product_variant_id', 'product_name_snapshot', 'variant_name_snapshot', 'variant_attributes_snapshot', 'sku_snapshot', 'quantity', 'unit_price', 'discount_amount', 'line_total', 'cost_price_snapshot'])]
class OrderItem extends Model
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
            'variant_attributes_snapshot' => 'array',
            'quantity' => 'integer',
            'unit_price' => 'string',
            'discount_amount' => 'string',
            'line_total' => 'string',
            'cost_price_snapshot' => 'string',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
