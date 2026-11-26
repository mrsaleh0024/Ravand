<?php

namespace App\Models;

use App\Concerns\HasUlid;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['tenant_id', 'parent_id', 'name', 'slug', 'description', 'sort_order', 'is_active'])]
class ProductCategory extends Model
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
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Parent category (chador → black chador trees, spec 5.3). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
