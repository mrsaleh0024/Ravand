<?php

namespace App\Models;

use App\Concerns\HasUlid;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-tenant loyalty settings singleton (phase 04, spec section 10).
 *
 * The single earn rule: every `amount_step` (integer minor units) of a
 * completed order's total grants `points_per_step` points. Points are only
 * awarded while `points_enabled` is true; disabling stops future awards but
 * never rewrites the ledger.
 *
 * @property string $id
 * @property string $tenant_id
 * @property bool $points_enabled
 * @property string $amount_step
 * @property int $points_per_step
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tenant_id', 'points_enabled', 'amount_step', 'points_per_step'])]
class LoyaltySetting extends Model
{
    use HasUlid;

    /** Every query on this model MUST stay inside one tenant (spec 2.2). */
    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function ($query): void {
            $context = app(TenantContext::class);

            if ($context->has()) {
                $query->where($query->getModel()->getTable().'.tenant_id', $context->tenant()->id);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'points_enabled' => 'boolean',
            'amount_step' => 'string',
            'points_per_step' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
