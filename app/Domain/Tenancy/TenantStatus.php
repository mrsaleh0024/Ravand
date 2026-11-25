<?php

namespace App\Domain\Tenancy;

/**
 * Lifecycle status of a tenant (store).
 */
enum TenantStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'فعال',
            self::Suspended => 'معلق',
            self::Archived => 'بایگانی‌شده',
        };
    }
}
