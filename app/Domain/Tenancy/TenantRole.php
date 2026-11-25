<?php

namespace App\Domain\Tenancy;

/**
 * Tenant-scoped roles.
 *
 * Owner: full control (settings, members, exports, everything).
 * Manager: manages operations, may view (not change) tenant settings and audit log.
 * Operator: data-entry only; no access to owner-only areas.
 *
 * Codes are stable English identifiers; only the current tenant role is
 * meaningful for authorization (evaluated server-side via TenantContext).
 */
enum TenantRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Operator = 'operator';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'مالک',
            self::Manager => 'مدیر',
            self::Operator => 'اپراتور',
        };
    }

    /** May view tenant settings (read-only for manager). */
    public function canViewSettings(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    /** May change tenant settings — owner only (phase 01 acceptance). */
    public function canUpdateSettings(): bool
    {
        return $this === self::Owner;
    }

    /** May read the tenant audit log. */
    public function canViewAuditLog(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }
}
