<?php

namespace Tests\Concerns;

use App\Domain\Sales\InventoryService;
use App\Domain\Sales\MovementType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;

/**
 * Shared catalog/stock seeding for phase-03 sales tests.
 *
 * Models are created directly (no HTTP) for speed; explicit tenant_id on
 * every row keeps the data honest even without a tenant context.
 */
trait SeedsCatalog
{
    /**
     * One category, one product, two variants (smoke check: «تعریف دسته،
     * محصول و دو variant»). Prices default to IRR integer strings.
     *
     * @return array{category: ProductCategory, product: Product, variantA: ProductVariant, variantB: ProductVariant}
     */
    protected function seedProduct(Tenant $tenant, array $overrides = []): array
    {
        $tenant->refresh(); // pick up DB defaults (default_currency …)

        /** @var ProductCategory $category */
        $category = ProductCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'چادر',
            'slug' => 'chador-'.substr(uniqid(), 0, 6),
            'sort_order' => 0,
            'is_active' => true,
        ]);

        /** @var Product $product */
        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => $overrides['product_name'] ?? 'چادر کرپ مدل ناز',
            'normalized_name' => $overrides['product_name'] ?? 'چادر کرپ مدل ناز',
            'track_inventory' => $overrides['track_inventory'] ?? true,
            'is_active' => true,
            'product_type' => 'physical',
        ]);

        /** @var ProductVariant $variantA */
        $variantA = ProductVariant::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'sku' => $overrides['sku_a'] ?? 'CH-NAZ-BLK-M',
            'variant_name' => 'مشکی سایز متوسط',
            'attributes' => [
                'color' => ['code' => 'black', 'label' => 'مشکی'],
                'size' => ['code' => 'm', 'label' => 'متوسط'],
                'fabric' => ['code' => 'crepe', 'label' => 'کرپ'],
            ],
            'sale_price' => $overrides['price_a'] ?? '1500000',
            'cost_price' => '1100000',
            'currency_code' => $tenant->default_currency,
            'is_active' => true,
        ]);

        /** @var ProductVariant $variantB */
        $variantB = ProductVariant::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'sku' => $overrides['sku_b'] ?? 'CH-NAZ-BLU-L',
            'variant_name' => 'سرمه‌ای سایز بزرگ',
            'attributes' => [
                'color' => ['code' => 'navy', 'label' => 'سرمه‌ای'],
                'size' => ['code' => 'l', 'label' => 'بزرگ'],
                'fabric' => ['code' => 'crepe', 'label' => 'کرپ'],
            ],
            'sale_price' => $overrides['price_b'] ?? '1700000',
            'cost_price' => null,
            'currency_code' => $tenant->default_currency,
            'is_active' => true,
        ]);

        return compact('category', 'product', 'variantA', 'variantB');
    }

    /** Register an opening stock movement (smoke: «ثبت موجودی اولیه»). */
    protected function addStock(User $actor, ProductVariant $variant, int $quantity): void
    {
        app(InventoryService::class)->record(
            variant: $variant,
            type: MovementType::Opening,
            quantityDelta: $quantity,
            idempotencyKey: 'seed:opening:'.$variant->id,
            actor: $actor,
        );
    }
}
