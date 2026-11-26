<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Audit\AuditLogger;
use App\Domain\Sales\Money;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Support\PersianText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Product catalog (phase 03, spec 5.3).
 *
 * Every product carries at least one variant — variants are what orders
 * snapshot (price, sku, attributes). Amounts arrive as user-typed strings
 * (possibly with Persian digits) and are normalized to integer strings
 * before storage; the currency is always the tenant's current currency.
 */
class ProductController extends Controller
{
    private const PAGE_SIZE = 20;

    /** Structured attribute keys offered as form fields (spec 5.3). */
    public const ATTRIBUTE_KEYS = ['color', 'size', 'chador_qovare', 'height', 'fabric'];

    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    /** Product list with variants, server-side search/filter (spec 6.6). */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $categoryId = (string) $request->query('category', '');
        $activeFilter = (string) $request->query('active', '');

        $query = Product::query()
            ->with(['variants' => fn ($q) => $q->orderBy('created_at'), 'category']);

        if ($search !== '') {
            $normalized = PersianText::normalize($search);
            $digits = PersianText::digitsOnly($search);

            $query->where(function ($q) use ($normalized, $digits): void {
                $q->where('normalized_name', 'like', "%{$normalized}%");

                if ($digits !== '') {
                    $q->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', "%{$digits}%"));
                }
            });
        }

        if ($categoryId !== '') {
            $query->where('category_id', $categoryId);
        }

        if ($activeFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($activeFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $page = $query
            ->orderByDesc('created_at')
            ->paginate(self::PAGE_SIZE)
            ->withQueryString();

        return Inertia::render('catalog/Index', [
            'products' => $page->through(fn (Product $product): array => $this->row($product)),
            'categories' => $this->categoryOptions(),
            'filters' => [
                'search' => $search,
                'category' => $categoryId,
                'active' => $activeFilter,
            ],
            'currency' => $this->context->tenant()->default_currency,
        ]);
    }

    /** Create a product with its initial variants in one transaction. */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $product = DB::transaction(function () use ($validated, $request): Product {
            /** @var Product $product */
            $product = Product::query()->create([
                'tenant_id' => $this->context->tenant()->id,
                'category_id' => $validated['category_id'] ?? null,
                'name' => $validated['name'],
                'normalized_name' => PersianText::normalize($validated['name']),
                'description' => $validated['description'] ?? null,
                'brand' => $validated['brand'] ?? null,
                'product_type' => 'physical',
                'is_active' => $validated['is_active'] ?? true,
                'track_inventory' => $validated['track_inventory'] ?? true,
            ]);

            $this->upsertVariants($product, $validated['variants'], $request->user()?->id);

            return $product;
        });

        $this->audit->log(
            action: 'product.created',
            tenant: $this->context->tenant(),
            actor: $request->user(),
            entityType: 'product',
            entityId: $product->id,
            metadata: [
                'name' => $product->name,
                'variants_count' => count($validated['variants']),
                'track_inventory' => $product->track_inventory,
            ],
        );

        return back()->with('success', 'محصول «'.$product->name.'» ثبت شد.');
    }

    /** Edit product fields and sync variants (add/edit/deactivate). */
    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        DB::transaction(function () use ($validated, $product): void {
            $existingIds = $product->variants()->pluck('id')->all();

            $product->update([
                'category_id' => $validated['category_id'] ?? null,
                'name' => $validated['name'],
                'normalized_name' => PersianText::normalize($validated['name']),
                'description' => $validated['description'] ?? null,
                'brand' => $validated['brand'] ?? null,
                'is_active' => $validated['is_active'] ?? $product->is_active,
                'track_inventory' => $validated['track_inventory'] ?? $product->track_inventory,
            ]);

            $this->upsertVariants($product, $validated['variants']);

            // Variants missing from the payload are removed ONLY when no
            // order item or stock movement ever referenced them; otherwise
            // they are deactivated — sales history stays intact.
            $keptIds = collect($validated['variants'])->pluck('id')->filter()->all();

            foreach (array_diff($existingIds, $keptIds) as $removedId) {
                /** @var ProductVariant|null $variant */
                $variant = $product->variants()->whereKey($removedId)->first();

                if ($variant === null) {
                    continue;
                }

                $used = OrderItem::query()->where('product_variant_id', $variant->id)->exists()
                    || $variant->movements()->exists();

                if ($used) {
                    $variant->update(['is_active' => false]);
                } else {
                    $variant->delete();
                }
            }
        });

        $this->audit->log(
            action: 'product.updated',
            tenant: $this->context->tenant(),
            actor: $request->user(),
            entityType: 'product',
            entityId: $product->id,
            metadata: [
                'name' => $product->name,
                'variants_count' => count($validated['variants']),
            ],
        );

        return back()->with('success', 'محصول به‌روزرسانی شد.');
    }

    /** Activate/deactivate a whole product (variants keep their own flags). */
    public function toggle(Request $request, Product $product): RedirectResponse
    {
        $product->update(['is_active' => ! $product->is_active]);

        $this->audit->log(
            action: $product->is_active ? 'product.activated' : 'product.deactivated',
            tenant: $this->context->tenant(),
            actor: $request->user(),
            entityType: 'product',
            entityId: $product->id,
            metadata: ['name' => $product->name],
        );

        return back()->with('success', $product->is_active ? 'محصول فعال شد.' : 'محصول غیرفعال شد.');
    }

    // --- internals --------------------------------------------------------

    /** @return array<string, mixed> */
    private function rules(): array
    {
        $tenantId = $this->context->tenant()->id;

        return [
            'name' => ['required', 'string', 'max:160'],
            'category_id' => ['nullable', 'string', 'max:26'],
            'description' => ['nullable', 'string', 'max:2000'],
            'brand' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'track_inventory' => ['nullable', 'boolean'],
            'variants' => ['required', 'array', 'min:1', 'max:50'],
            'variants.*.id' => ['nullable', 'string', 'max:26'],
            'variants.*.variant_name' => ['nullable', 'string', 'max:160'],
            'variants.*.sku' => [
                'nullable', 'string', 'max:100',
                // SKU uniqueness is tenant-scoped; NULL skus never collide.
                function (string $attribute, mixed $value, \Closure $fail) use ($tenantId): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $index = explode('.', $attribute)[1];
                    $ignoreId = request()->input("variants.{$index}.id");

                    $taken = ProductVariant::query()
                        ->where('tenant_id', $tenantId)
                        ->where('sku', $value)
                        ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                        ->exists();

                    if ($taken) {
                        $fail('این کد کالا قبلاً استفاده شده است.');
                    }
                },
            ],
            'variants.*.attributes' => ['nullable', 'array', 'max:'.count(self::ATTRIBUTE_KEYS)],
            'variants.*.attributes.*.code' => ['nullable', 'string', 'max:60'],
            'variants.*.attributes.*.label' => ['nullable', 'string', 'max:60'],
            'variants.*.sale_price' => ['required', 'string', 'max:30', 'regex:/^[\d۰-۹٠-٩,،\s]+$/'],
            'variants.*.cost_price' => ['nullable', 'string', 'max:30', 'regex:/^[\d۰-۹٠-٩,،\s]+$/'],
            'variants.*.is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Normalize a raw amount field to an integer string (Persian digits
     * and thousand separators tolerated), or null.
     */
    private function amountOrNull(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return Money::normalize($raw);
    }

    /**
     * @param  array<int, array<string, mixed>>  $variants
     */
    private function upsertVariants(Product $product, array $variants): void
    {
        $currency = $this->context->tenant()->default_currency;

        foreach ($variants as $row) {
            $attributes = null;

            if (is_array($row['attributes'] ?? null)) {
                $clean = [];

                foreach ($row['attributes'] as $key => $value) {
                    if (! in_array($key, self::ATTRIBUTE_KEYS, true) || ! is_array($value)) {
                        continue;
                    }

                    $code = trim((string) ($value['code'] ?? ''));
                    $label = trim((string) ($value['label'] ?? ''));

                    if ($code === '' && $label === '') {
                        continue;
                    }

                    $clean[$key] = ['code' => $code, 'label' => $label];
                }

                $attributes = $clean ?: null;
            }

            $payload = [
                'variant_name' => $row['variant_name'] ?? null,
                'sku' => ($row['sku'] ?? '') !== '' ? $row['sku'] : null,
                'attributes' => $attributes,
                'sale_price' => Money::normalize($row['sale_price']),
                'cost_price' => $this->amountOrNull($row['cost_price'] ?? null),
                'currency_code' => $currency,
                'is_active' => $row['is_active'] ?? true,
            ];

            if (! empty($row['id'])) {
                $product->variants()->whereKey($row['id'])->first()?->update($payload);

                continue;
            }

            $product->variants()->create(array_merge(
                ['tenant_id' => $this->context->tenant()->id],
                $payload,
            ));
        }
    }

    /** @return array<string, mixed> */
    private function row(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'brand' => $product->brand,
            'category_id' => $product->category_id,
            'category_name' => $product->category?->name,
            'is_active' => $product->is_active,
            'track_inventory' => $product->track_inventory,
            'min_price' => $product->minPrice(),
            'created_at' => $product->created_at?->toISOString(),
            'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'variant_name' => $variant->variant_name,
                'sku' => $variant->sku,
                'attributes' => $variant->attributes,
                'sale_price' => $variant->sale_price,
                'cost_price' => $variant->cost_price,
                'currency_code' => $variant->currency_code,
                'stock_on_hand' => $variant->stock_on_hand,
                'is_active' => $variant->is_active,
            ])->all(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function categoryOptions(): array
    {
        return ProductCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ProductCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'parent_id' => $category->parent_id,
                'is_active' => $category->is_active,
            ])
            ->all();
    }
}
