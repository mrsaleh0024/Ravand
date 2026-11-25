<?php

namespace App\Domain\Identity;

/**
 * Account-level status of a human user.
 *
 * Codes are stable English identifiers (data standard, spec section 4.2);
 * Persian labels are presentation-only.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';

    /** Persian display label (UI only — never persisted or exported as label). */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'فعال',
            self::Suspended => 'معلق',
            self::Archived => 'بایگانی‌شده',
        };
    }
}
