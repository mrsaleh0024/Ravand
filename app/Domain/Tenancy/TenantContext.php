<?php

namespace App\Domain\Tenancy;

use App\Models\Tenant;
use App\Models\TenantUser;
use RuntimeException;

/**
 * Holds the resolved tenant + membership for the current HTTP request.
 *
 * The context is filled ONLY by the EnsureTenantContext middleware from a
 * verified, active membership of the authenticated user. It is never filled
 * from client-supplied identifiers (spec section 2.2 — security critical).
 */
class TenantContext
{
    protected ?Tenant $tenant = null;

    protected ?TenantUser $membership = null;

    public function set(Tenant $tenant, TenantUser $membership): void
    {
        $this->tenant = $tenant;
        $this->membership = $membership;
    }

    public function forget(): void
    {
        $this->tenant = null;
        $this->membership = null;
    }

    public function has(): bool
    {
        return $this->tenant !== null && $this->membership !== null;
    }

    /** @throws RuntimeException when tenant context was not resolved (callers must run behind the `tenant` middleware). */
    public function tenant(): Tenant
    {
        if ($this->tenant === null) {
            throw new RuntimeException('Tenant context is not resolved for this request.');
        }

        return $this->tenant;
    }

    /** @throws RuntimeException when tenant context was not resolved. */
    public function membership(): TenantUser
    {
        if ($this->membership === null) {
            throw new RuntimeException('Tenant context is not resolved for this request.');
        }

        return $this->membership;
    }

    /** Current role inside the resolved tenant, or null when context is missing. */
    public function role(): ?TenantRole
    {
        return $this->membership?->role;
    }
}
