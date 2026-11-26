<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesTenancy;
use Tests\Concerns\SeedsCatalog;
use Tests\TestCase;

/**
 * Phase-03 catalog smoke checks (spec section 10, phase 03):
 * category/product/two-variants definition, tenant-scoped SKU uniqueness,
 * Persian search, role limits, tenant isolation.
 */
class CatalogTest extends TestCase
{
    use CreatesTenancy, RefreshDatabase, SeedsCatalog;

    public function test_category_product_and_two_variants_can_be_defined(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه کاتالوگ');

        $response = $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('product-categories.store'), ['name' => 'چادر']);

        $response->assertRedirect();
        $categoryId = ProductCategory::query()->where('tenant_id', $tenant->id)->firstOrFail()->id;

        $response = $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('products.store'), [
                'name' => 'چادر کرپ مدل ناز',
                'category_id' => $categoryId,
                'variants' => [
                    [
                        'variant_name' => 'مشکی',
                        'sku' => 'CH-01',
                        'sale_price' => '1500000',
                        'attributes' => ['color' => ['code' => 'black', 'label' => 'مشکی']],
                    ],
                    [
                        'variant_name' => 'سرمه‌ای',
                        'sku' => 'CH-02',
                        'sale_price' => '۱۷۰۰۰۰۰', // Persian digits tolerated
                        'attributes' => ['color' => ['code' => 'navy', 'label' => 'سرمه‌ای']],
                    ],
                ],
            ]);

        $response->assertRedirect()->assertSessionHas('success');

        $product = Product::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertSame('چادر کرپ مدل ناز', $product->name);
        $this->assertCount(2, $product->variants);

        $prices = $product->variants->pluck('sale_price')->sort()->values();
        $this->assertSame(['1500000', '1700000'], $prices->all());
        $this->assertSame($tenant->refresh()->default_currency, $product->variants->first()->currency_code);
        $this->assertSame(0, $product->variants->first()->stock_on_hand);
    }

    public function test_sku_must_be_unique_within_a_tenant_only(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه یک');
        [$otherOwner, $otherTenant] = $this->createTenantWithUser('فروشگاه دو', 'owner');

        $seed = $this->seedProduct($tenant, ['sku_a' => 'SHARED-SKU']);

        // Same SKU in the same tenant → rejected.
        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('products.store'), [
                'name' => 'محصول دیگر',
                'variants' => [
                    ['variant_name' => 'قرمز', 'sku' => 'SHARED-SKU', 'sale_price' => '100'],
                ],
            ])
            ->assertSessionHasErrors('variants.0.sku');

        // Same SKU in another tenant → fine (spec 2.2: uniqueness per tenant).
        $response = $this->actingAs($otherOwner)
            ->withCurrentTenant($otherOwner, $otherTenant)
            ->post(route('products.store'), [
                'name' => 'محصول فروشگاه دو',
                'variants' => [
                    ['variant_name' => 'قرمز', 'sku' => 'SHARED-SKU', 'sale_price' => '100'],
                ],
            ]);

        $response->assertRedirect();
        $this->assertSame(1, Product::query()->where('tenant_id', $otherTenant->id)->count());
        $this->assertSame('SHARED-SKU', $seed['variantA']->refresh()->sku);
    }

    public function test_product_search_matches_persian_digits_and_names(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه جست‌وجو');
        $this->seedProduct($tenant, ['sku_a' => 'SKU-777', 'product_name' => 'روسری ابریشم']);

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->get(route('products.index', ['search' => 'روسری']))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('catalog/Index')
                    ->has('products.data', 1),
            );

        // SKU search with Persian digits finds the same product.
        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->get(route('products.index', ['search' => '۷۷۷']))
            ->assertInertia(
                fn (Assert $page) => $page->has('products.data', 1),
            );
    }

    public function test_operator_cannot_manage_the_catalog_but_may_read_it(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه نقش‌ها');
        $operator = User::factory()->create();
        $this->attachMembership($operator, $tenant, 'operator');

        $payload = [
            'name' => 'محصول تست',
            'variants' => [['variant_name' => 'تنوع', 'sale_price' => '1000']],
        ];

        $this->actingAs($operator)
            ->withCurrentTenant($operator, $tenant)
            ->post(route('products.store'), $payload)
            ->assertForbidden();

        // Reading the catalog stays open (needed to record sales).
        $this->actingAs($operator)
            ->withCurrentTenant($operator, $tenant)
            ->get(route('products.index'))
            ->assertOk();
    }

    public function test_catalog_is_tenant_isolated(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه الف');
        [$ownerB, $tenantB] = $this->createOwner('فروشگاه ب');
        $seed = $this->seedProduct($tenant);

        // Tenant B sees none of tenant A's products.
        $this->actingAs($ownerB)
            ->withCurrentTenant($ownerB, $tenantB)
            ->get(route('products.index'))
            ->assertInertia(
                fn (Assert $page) => $page->has('products.data', 0),
            );

        // Cross-tenant product update attempt → 404 (binding is scoped).
        $this->actingAs($ownerB)
            ->withCurrentTenant($ownerB, $tenantB)
            ->put(route('products.update', ['product' => $seed['product']->id]), [
                'name' => 'هک',
                'variants' => [['sale_price' => '1']],
            ])
            ->assertNotFound();
    }

    public function test_removing_a_used_variant_deactivates_it_instead_of_deleting(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه ویرایش');
        $seed = $this->seedProduct($tenant);

        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'customer_number' => 'CUS-000001',
            'display_name' => 'تست',
            'normalized_name' => 'تست',
            'customer_status' => 'active',
            'acquisition_channel' => 'instagram',
            'preferred_language' => 'fa',
        ]);

        $order = Order::query()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'order_number' => 'ORD-000001',
            'sales_channel' => 'instagram',
            'fulfillment_method' => 'in_person',
            'status' => 'draft',
            'placed_at' => now(),
            'subtotal_amount' => '0',
            'discount_amount' => '0',
            'shipping_amount' => '0',
            'total_amount' => '0',
            'currency_code' => 'IRR',
        ]);

        OrderItem::query()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'product_variant_id' => $seed['variantA']->id,
            'product_name_snapshot' => 'چادر',
            'quantity' => 1,
            'unit_price' => '0',
            'discount_amount' => '0',
            'line_total' => '0',
        ]);

        // Edit keeps only variant B.
        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->put(route('products.update', ['product' => $seed['product']->id]), [
                'name' => 'چادر کرپ مدل ناز',
                'variants' => [
                    [
                        'id' => $seed['variantB']->id,
                        'variant_name' => 'سرمه‌ای',
                        'sku' => 'CH-NAZ-BLU-L',
                        'sale_price' => '1700000',
                    ],
                ],
            ])
            ->assertRedirect();

        $seed['variantA']->refresh();

        $this->assertFalse($seed['variantA']->is_active, 'used variant must be deactivated, not deleted');
        $this->assertDatabaseHas('product_variants', ['id' => $seed['variantA']->id]);
    }
}
