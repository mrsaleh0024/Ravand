<?php

namespace App\Models;

use App\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's social identity (spec 5.2). Handles change over time; the
 * history stays traceable through the audit log.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $customer_id
 * @property string $platform
 * @property string $username
 * @property string $normalized_handle
 * @property string|null $profile_url
 * @property string|null $display_name
 * @property bool $is_primary
 */
#[Fillable(['tenant_id', 'customer_id', 'platform', 'username', 'normalized_handle', 'profile_url', 'display_name', 'is_primary', 'notes'])]
class CustomerSocialAccount extends Model
{
    use HasUlid;

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
