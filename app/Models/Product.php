<?php

namespace App\Models;

use App\Concerns\HasUlid;
use App\Domain\Sales\ProductType;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $category_id
 * @property string $name
 * @property string $normalized_name
 * @property string|null $description
 * @property string|null $brand
 * @property ProductType $product_type
 * @property bool $is_active
 * @property bool $track_inventory
 */
#[Fillable(['tenant_id', 'category_id', 'name', 'normalized_name', 'description', 'brand', 'product_type', 'is_active', 'track_inventory'])]
class Product extends Model
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
            'product_type' => ProductType::class,
            'is_active' => 'boolean',
            'track_inventory' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    /** Cheapest active variant price (string amount) for list views. */
    public function minPrice(): ?string
    {
        $price = $this->variants->filter(fn (ProductVariant $variant): bool => $variant->is_active)->min('sale_price');

        return $price === null ? null : (string) $price;
    }
}
