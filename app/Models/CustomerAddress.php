<?php

namespace App\Models;

use App\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer shipping address (spec 5.2). Full address lines are private:
 * they appear in the profile view only, never in the customer list.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $customer_id
 * @property string $title
 * @property string|null $recipient_name
 * @property string|null $recipient_phone
 * @property string|null $province
 * @property string|null $city
 * @property string|null $district
 * @property string|null $postal_code
 * @property string|null $address_line
 * @property string|null $delivery_notes
 * @property bool $is_default
 */
#[Fillable(['tenant_id', 'customer_id', 'title', 'recipient_name', 'recipient_phone', 'province', 'city', 'district', 'postal_code', 'address_line', 'delivery_notes', 'is_default'])]
class CustomerAddress extends Model
{
    use HasUlid;

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
