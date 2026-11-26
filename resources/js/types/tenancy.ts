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

export type Member = {
    id: string;
    user_id: string;
    name: string | null;
    phone: string | null;
    status: 'active' | 'suspended' | 'revoked';
    role: TenantRole;
    must_change_password: boolean;
    last_login_at: string | null;
    joined_at: string | null;
    is_self: boolean;
};

export type ResetRequestItem = {
    id: string;
    phone: string;
    status: 'pending' | 'approved' | 'rejected';
    requested_at: string | null;
    handled_at: string | null;
    requested_by: { id: string; name: string } | null;
    handled_by: string | null;
};

/** Live dashboard payload (GET /panel/live, polled every 10 seconds). */
export type LiveEvent = {
    id: string;
    action: string;
    occurred_at: string | null;
    ip_address: string | null;
    actor: string | null;
};

export type LoginSeriesPoint = {
    date: string;
    label: string;
    logins: number;
    failed: number;
};

export type SystemHealth = {
    php_version: string;
    laravel_version: string;
    environment: string;
    debug: boolean;
    db_driver: string;
    cache_driver: string;
    queue_driver: string;
    session_driver: string;
    bale_code_bot: boolean;
    bale_admin_bot: boolean;
    owner_2fa: boolean;
    failed_jobs: number;
    cache_ok: boolean;
    log_tail: string[];
};

export type LivePayload = {
    generated_at: string;
    events: LiveEvent[];
    chart: { series: LoginSeriesPoint[] };
    stats: {
        logins_today: number;
        failed_today: number;
        active_members: number;
        by_role: Record<TenantRole, number>;
        pending_reset_requests: number;
    };
    system?: SystemHealth;
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
