<?php

namespace App\Models;

use App\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Seller-typed interaction summary (spec 5.2). Full social-media message
 * content is intentionally NOT stored — only the summary the seller types.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $customer_id
 * @property string $interaction_type
 * @property string $channel
 * @property string|null $subject
 * @property string $summary
 * @property string|null $outcome
 * @property string|null $follow_up_at
 * @property string|null $follow_up_status
 * @property string|null $related_order_id
 * @property string|null $created_by_user_id
 */
#[Fillable(['tenant_id', 'customer_id', 'interaction_type', 'channel', 'subject', 'summary', 'outcome', 'occurred_at', 'follow_up_at', 'follow_up_status', 'related_order_id', 'created_by_user_id'])]
class CustomerInteraction extends Model
{
    use HasUlid;

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'follow_up_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
