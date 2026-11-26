<?php

namespace App\Models;

use App\Concerns\HasUlid;
use App\Domain\Customers\CustomerStatus;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $customer_number
 * @property string $display_name
 * @property string $normalized_name
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $gender
 * @property string|null $birth_date
 * @property CustomerStatus $customer_status
 * @property string $acquisition_channel
 * @property string|null $acquisition_note
 * @property string|null $preferred_contact_channel
 * @property string $preferred_language
 * @property string|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tenant_id', 'customer_number', 'display_name', 'normalized_name', 'first_name', 'last_name', 'gender', 'birth_date', 'customer_status', 'acquisition_channel', 'acquisition_note', 'preferred_contact_channel', 'preferred_language', 'created_by_user_id'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, HasUlid;

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'customer_status' => CustomerStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

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

    /** Tenant of this customer. */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Panel user who first recorded the customer. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    /** Best contact row for list views: primary flag first, newest first. */
    public function primaryContact()
    {
        return $this->hasOne(CustomerContact::class)
            ->orderByDesc('is_primary')
            ->orderByDesc('created_at');
    }

    /** Default address for list views (city column only is displayed there). */
    public function defaultAddress()
    {
        return $this->hasOne(CustomerAddress::class)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at');
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(CustomerSocialAccount::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(CustomerConsent::class);
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(CustomerPreference::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(CustomerMeasurement::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class)->latest();
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(CustomerInteraction::class)->latest('occurred_at');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        // No withTimestamps(): the pivot carries created_at only (spec 5.2).
        return $this->belongsToMany(Tag::class, 'customer_tag')
            ->using(CustomerTagPivot::class);
    }
}
