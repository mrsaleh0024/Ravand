<?php

namespace App\Models;

use App\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plain-text customer note (spec 5.2). Raw HTML is never accepted nor
 * rendered; private notes are visible to owner/manager only (spec 8.5).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $customer_id
 * @property string $body
 * @property string $visibility
 * @property string|null $created_by_user_id
 */
#[Fillable(['tenant_id', 'customer_id', 'body', 'visibility', 'created_by_user_id'])]
class CustomerNote extends Model
{
    use HasUlid;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
