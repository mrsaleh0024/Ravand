<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * customer_tag pivot row (spec 5.2). Kept as a class so ULID keys and the
 * tenant guard columns are handled explicitly. The table carries created_at
 * ONLY (no updated_at), so model timestamps are disabled and created_at is
 * passed explicitly by CustomerTagController::attach().
 */
class CustomerTagPivot extends Pivot
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $table = 'customer_tag';

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
