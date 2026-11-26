<?php

namespace App\Models;

use App\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Communication consent record (spec 5.2, 8.5). Marketing consent is never
 * implied by recording a phone number: the default status is `unknown`.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $customer_id
 * @property string $channel
 * @property string $purpose
 * @property string $status
 * @property string $capture_method
 * @property string|null $evidence_note
 * @property string|null $captured_by_user_id
 */
#[Fillable(['tenant_id', 'customer_id', 'channel', 'purpose', 'status', 'captured_at', 'capture_method', 'evidence_note', 'captured_by_user_id'])]
class CustomerConsent extends Model
{
    use HasUlid;

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
