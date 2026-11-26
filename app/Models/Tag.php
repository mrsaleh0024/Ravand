<?php

namespace App\Models;

use App\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Tenant-level tag (spec 5.2): a shared vocabulary applied to customers.
 * Tags complement — never replace — status or transactional data.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $slug
 * @property string|null $color_token
 */
#[Fillable(['tenant_id', 'name', 'slug', 'color_token'])]
class Tag extends Model
{
    use HasUlid;

    public function customers(): BelongsToMany
    {
        // No withTimestamps(): the pivot carries created_at only (spec 5.2).
        return $this->belongsToMany(Customer::class, 'customer_tag')
            ->using(CustomerTagPivot::class);
    }
}
