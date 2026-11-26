<?php

namespace App\Domain\Tenancy;

/**
 * Status of a user membership inside a tenant. Display labels live in the
 * UI layer.
 */
enum MembershipStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
}
