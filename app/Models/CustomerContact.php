<?php

namespace App\Models;

use App\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of a customer's contact channels (spec 5.2). The value is NEVER
 * unique — family members may share a number; duplicates surface as a
 * warning in the UI (spec 7.2).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $customer_id
 * @property string $contact_type
 * @property string|null $label
 * @property string $display_value
 * @property string $normalized_value
 * @property string|null $country_code
 * @property bool $is_primary
 * @property bool $is_verified
 */
#[Fillable(['tenant_id', 'customer_id', 'contact_type', 'label', 'display_value', 'normalized_value', 'country_code', 'is_primary', 'is_verified', 'notes'])]
class CustomerContact extends Model
{
    use HasUlid;

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
