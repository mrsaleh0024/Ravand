<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Audit\AuditLogger;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Support\PersianText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Product category maintenance (phase 03, spec 5.3): a small parent/child
 * tree (chador → black chador …). Categories are never hard-deleted in the
 * UI; they are deactivated — products keep their historical link.
 */
class ProductCategoryController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'string', 'max:26'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $tenantId = $this->context->tenant()->id;
        $parentId = $validated['parent_id'] ?? null;

        if ($parentId !== null) {
            // Parent must live in the same tenant (global scope enforces it).
            ProductCategory::query()->findOrFail($parentId);
        }

        $slug = Str::slug(PersianText::normalize($validated['name']), '-', 'en')
            ?: substr(md5($validated['name'].mt_rand()), 0, 10);

        // Slugs stay unique per tenant (spec 5.3).
        $base = $slug;
        $suffix = 2;

        while (ProductCategory::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        /** @var ProductCategory $category */
        $category = ProductCategory::query()->create([
            'tenant_id' => $tenantId,
            'parent_id' => $parentId,
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => true,
        ]);

        $this->audit->log(
            action: 'product_category.created',
            tenant: $this->context->tenant(),
            actor: $request->user(),
            entityType: 'product_category',
            entityId: $category->id,
            metadata: ['name' => $category->name, 'parent_id' => $category->parent_id],
        );

        return back()->with('success', 'دسته «'.$category->name.'» ثبت شد.');
    }

    public function update(Request $request, ProductCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'string', 'max:26'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $parentId = $validated['parent_id'] ?? null;

        if ($parentId !== null) {
            if ($parentId === $category->id) {
                return back()->withErrors(['parent_id' => 'دسته نمی‌تواند والد خودش باشد.']);
            }

            $parent = ProductCategory::query()->findOrFail($parentId);

            // Guard against cycles: walk up the chain.
            $cursor = $parent;
            $depth = 0;

            while ($cursor->parent_id !== null && $depth < 20) {
                if ($cursor->parent_id === $category->id) {
                    return back()->withErrors(['parent_id' => 'زنجیره والد/فرزندی معتبر نیست.']);
                }

                $cursor = ProductCategory::query()->findOrFail($cursor->parent_id);
                $depth++;
            }
        }

        $category->update([
            'name' => $validated['name'],
            'parent_id' => $parentId,
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? $category->sort_order,
            'is_active' => $validated['is_active'] ?? $category->is_active,
        ]);

        $changes = array_keys($category->getChanges());

        $this->audit->log(
            action: 'product_category.updated',
            tenant: $this->context->tenant(),
            actor: $request->user(),
            entityType: 'product_category',
            entityId: $category->id,
            metadata: ['name' => $category->name, 'changed' => $changes],
        );

        return back()->with('success', 'دسته به‌روزرسانی شد.');
    }

    /** Deactivate/reactivate (never a hard delete — history keeps the link). */
    public function toggle(Request $request, ProductCategory $category): RedirectResponse
    {
        $category->update(['is_active' => ! $category->is_active]);

        $this->audit->log(
            action: $category->is_active ? 'product_category.activated' : 'product_category.deactivated',
            tenant: $this->context->tenant(),
            actor: $request->user(),
            entityType: 'product_category',
            entityId: $category->id,
            metadata: ['name' => $category->name],
        );

        return back()->with('success', $category->is_active ? 'دسته فعال شد.' : 'دسته غیرفعال شد.');
    }
}
