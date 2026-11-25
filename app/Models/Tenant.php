<?php

namespace App\Models;

use App\Concerns\HasUlid;
use App\Domain\Tenancy\TenantStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property TenantStatus $status
 * @property string $default_currency
 * @property string $timezone
 * @property string $locale
 * @property string $calendar
 * @property array<string, mixed>|null $settings
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUlid;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'default_currency',
        'timezone',
        'locale',
        'calendar',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'settings' => 'array',
        ];
    }

    /** Members (users) attached to this tenant.
     * @return BelongsToMany<User, $this, TenantUser>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->withPivot('role', 'status', 'joined_at')
            ->withTimestamps()
            ->using(TenantUser::class);
    }

    /** @return HasMany<TenantUser, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }
}
