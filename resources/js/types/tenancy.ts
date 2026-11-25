/**
 * Tenancy shared types (phase 01). Field names mirror the server contract:
 * stable English snake_case for codes, values may be Persian.
 */

export type TenantRole = 'owner' | 'manager' | 'operator';

export type Membership = {
    tenant_id: string;
    tenant_name: string | null;
    role: TenantRole;
};

export type TenantInfo = {
    id: string;
    name: string;
    slug: string;
    default_currency: string;
    timezone: string;
};

export type AuditEntry = {
    id: string;
    action: string;
    entity_type: string | null;
    entity_id: string | null;
    occurred_at: string | null;
    ip_address: string | null;
    metadata: Record<string, unknown> | null;
    actor: { id: string; name: string } | null;
};

export type PaginatedLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginatedEntries<T> = {
    data: T[];
    links: PaginatedLink[];
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
};
