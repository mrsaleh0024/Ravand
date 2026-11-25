<?php

namespace App\Domain\Tenancy;

/**
 * Lifecycle status of a tenant (store). Display labels live in the UI layer.
 */
enum TenantStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';
}
