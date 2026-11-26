<?php

namespace App\Models;

use App\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Body measurement row (spec 5.2): sizes/lengths as separate historical
 * rows — never squeezed into shared columns — so values can change over
 * time while keeping history.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $customer_id
 * @property string $measurement_code
 * @property string|null $value_text
 * @property string|null $value_number
 * @property string|null $unit
 * @property string $source
 * @property string|null $notes
 */
#[Fillable(['tenant_id', 'customer_id', 'measurement_code', 'value_text', 'value_number', 'unit', 'recorded_at', 'source', 'notes'])]
class CustomerMeasurement extends Model
{
    use HasUlid;

    protected function casts(): array
    {
        return [
            'value_number' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
