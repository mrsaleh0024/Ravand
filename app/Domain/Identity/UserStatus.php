<?php

namespace App\Domain\Identity;

/**
 * Account-level status of a human user.
 *
 * Codes are stable English identifiers (data standard, spec section 4.2);
 * display labels live in the UI layer only.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';
}
