<?php

namespace App\Models;

use App\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Structured preference row (spec 5.2): category + stable English value
 * code + Persian label snapshot. Purchase-inferred rows are distinguishable
 * from customer-stated ones through `source`.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $customer_id
 * @property string $preference_category
 * @property string $value_code
 * @property string $value_label
 * @property string|null $preference_level
 * @property string $source
 * @property int|null $confidence
 * @property string|null $notes
 */
#[Fillable(['tenant_id', 'customer_id', 'preference_category', 'value_code', 'value_label', 'preference_level', 'source', 'confidence', 'notes'])]
class CustomerPreference extends Model
{
    use HasUlid;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
