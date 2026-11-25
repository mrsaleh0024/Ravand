<?php

namespace App\Domain\Tenancy;

/**
 * Status of a user membership inside a tenant.
 */
enum MembershipStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'فعال',
            self::Revoked => 'لغو‌شده',
        };
    }
}
