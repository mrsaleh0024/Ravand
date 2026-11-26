import type { TagItem } from './customers';

/**
 * Loyalty club, dashboard KPI and customer-report types (phase 04).
 *
 * Amount fields are integer strings (DECIMAL(20,0)) — never JS numbers,
 * so rial/toman values beyond 2^53 stay exact.
 */

export type LoyaltyTransactionKind = 'earn' | 'reverse' | 'adjust';

export type LoyaltySettings = {
    points_enabled: boolean;
    amount_step: string;
    points_per_step: number;
};

export type LoyaltyTransactionRow = {
    id: string;
    type: LoyaltyTransactionKind;
    points: number;
    amount_basis: string | null;
    reason: string | null;
    order_number: string | null;
    actor_name: string | null;
    created_at: string | null;
};

export type LoyaltyProfile = {
    enabled: boolean;
    balance: number;
    earned: number;
    reversed: number;
    adjusted: number;
    last_transaction_at: string | null;
    transactions: LoyaltyTransactionRow[];
};

export type KpiWindow = '7d' | '30d' | '90d';

export type KpiPayload = {
    window: KpiWindow;
    currency_code: string;
    kpis: {
        customers_total: number;
        orders_count: number;
        completed_sales: string;
        average_order_value: string | null;
        new_customers: number;
    };
    generated_at: string;
};

export type ReportSort = 'last_purchase' | 'purchase_count' | 'total_spent' | 'name';

export type CustomerReportRow = {
    id: string;
    customer_number: string;
    display_name: string;
    phone: string | null;
    tags: TagItem[];
    purchase_count: number;
    total_spent: string;
    last_purchase_at: string | null;
};
