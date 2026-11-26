<?php

namespace App\Models;

use App\Concerns\HasUlid;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sellable variant (color/size/fabric combination) of a product.
 *
 * `attributes` is a schema-controlled JSON object such as
 * {"color": {"code": "black", "label": "مشکی"}, "size": {...}} — the UI
 * keeps structured form fields for the known keys (spec 5.3). Order items
 * snapshot this payload at purchase time, so later edits never rewrite
 * sales history.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $product_id
 * @property string|null $sku
 * @property string|null $variant_name
 * @property array<string, mixed>|null $attributes
 * @property string $sale_price
 * @property string|null $cost_price
 * @property string $currency_code
 * @property bool $is_active
 * @property int $stock_on_hand cache — inventory_movements is the truth
 */
#[Fillable(['tenant_id', 'product_id', 'sku', 'variant_name', 'attributes', 'sale_price', 'cost_price', 'currency_code', 'is_active', 'stock_on_hand'])]
class ProductVariant extends Model
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
            'attributes' => 'array',
            'sale_price' => 'string',
            'cost_price' => 'string',
            'is_active' => 'boolean',
            'stock_on_hand' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /** @return HasMany<InventoryMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'product_variant_id');
    }

    /** Human label used in stock errors and order lines. */
    public function label(): string
    {
        return $this->variant_name
            ?: ($this->sku ?: ($this->product->name ?? 'کالا'));
    }
}
