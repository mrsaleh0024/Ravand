/**
 * Sales & inventory types (phase 03, spec 5.3/5.4/5.5).
 *
 * Amount fields are integer strings (DECIMAL(20,0)) — never JS numbers,
 * so rial/toman values beyond 2^53 stay exact.
 */

export type ProductType = 'physical';

export type VariantAttributes = Partial<
    Record<'color' | 'size' | 'chador_qovare' | 'height' | 'fabric', { code: string; label: string }>
>;

export type ProductVariantItem = {
    id: string;
    variant_name: string | null;
    sku: string | null;
    attributes: VariantAttributes | null;
    sale_price: string;
    cost_price: string | null;
    currency_code: string;
    stock_on_hand: number;
    is_active: boolean;
};

export type ProductRow = {
    id: string;
    name: string;
    description: string | null;
    brand: string | null;
    category_id: string | null;
    category_name: string | null;
    is_active: boolean;
    track_inventory: boolean;
    min_price: string | null;
    created_at: string | null;
    variants: ProductVariantItem[];
};

export type CategoryItem = {
    id: string;
    name: string;
    parent_id: string | null;
    is_active: boolean;
};

export type MovementTypeCode =
    | 'opening'
    | 'purchase_in'
    | 'sale_out'
    | 'return_in'
    | 'adjustment_in'
    | 'adjustment_out'
    | 'cancellation_restore';

export type StockRow = {
    id: string;
    label: string;
    product_name: string | null;
    sku: string | null;
    stock_on_hand: number;
    tracks: boolean;
    attributes: VariantAttributes | null;
};

export type MovementRow = {
    id: string;
    variant_label: string | null;
    product_name: string | null;
    movement_type: MovementTypeCode;
    quantity_delta: number;
    reason: string | null;
    reference_type: string | null;
    actor_name: string | null;
    occurred_at: string;
};

export type VariantOption = {
    id: string;
    label: string;
    product_name: string | null;
    stock_on_hand: number;
};

export type OrderStatus =
    | 'draft'
    | 'confirmed'
    | 'processing'
    | 'shipped'
    | 'delivered'
    | 'completed'
    | 'cancelled'
    | 'returned';

export type PaymentStatus = 'unpaid' | 'partially_paid' | 'paid' | 'refunded';

export type SalesChannelCode = 'instagram' | 'whatsapp' | 'telegram' | 'direct' | 'in_person' | 'other';

export type FulfillmentMethodCode = 'shipping' | 'pickup' | 'in_person';

export type PaymentMethodCode = 'cash' | 'card_to_card' | 'pos' | 'online' | 'bank_transfer' | 'other';

export type PaymentRecordStatus = 'recorded' | 'refunded' | 'voided';

export type OrderListItem = {
    id: string;
    order_number: string;
    status: OrderStatus;
    sales_channel: SalesChannelCode;
    customer_name: string | null;
    customer_number: string | null;
    items_count: number;
    total_amount: string;
    currency_code: string;
    payment_status: PaymentStatus;
    placed_at: string | null;
};

export type OrderFormVariant = {
    id: string;
    label: string;
    sku: string | null;
    attributes: VariantAttributes | null;
    sale_price: string;
    currency_code: string;
    stock_on_hand: number;
};

export type OrderFormProduct = {
    id: string;
    name: string;
    category_id: string | null;
    track_inventory: boolean;
    variants: OrderFormVariant[];
};

export type CustomerLookupAddress = {
    id: string;
    title: string;
    city: string | null;
    province: string | null;
    address_line: string | null;
    postal_code: string | null;
    recipient_name: string | null;
    is_default: boolean;
};

export type CustomerLookupMatch = {
    id: string;
    customer_number: string;
    display_name: string;
    phone: string | null;
    city: string | null;
    addresses: CustomerLookupAddress[];
};

export type OrderShowItem = {
    id: string;
    product_name: string;
    variant_name: string | null;
    attributes: VariantAttributes | null;
    sku: string | null;
    quantity: number;
    unit_price: string;
    discount_amount: string;
    line_total: string;
};

export type OrderShowPayment = {
    id: string;
    amount: string;
    currency_code: string;
    method: PaymentMethodCode;
    status: PaymentRecordStatus;
    paid_at: string | null;
    reference_code: string | null;
    note: string | null;
    creator_name: string | null;
};

export type OrderShowMovement = {
    id: string;
    variant_label: string | null;
    movement_type: MovementTypeCode;
    quantity_delta: number;
    occurred_at: string;
};

export type OrderShowPayload = {
    id: string;
    order_number: string;
    status: OrderStatus;
    sales_channel: SalesChannelCode;
    fulfillment_method: FulfillmentMethodCode;
    customer: { id: string; display_name: string; customer_number: string };
    placed_at: string | null;
    completed_at: string | null;
    returned_at: string | null;
    return_note: string | null;
    subtotal_amount: string;
    discount_amount: string;
    shipping_amount: string;
    total_amount: string;
    currency_code: string;
    payment_status: PaymentStatus;
    paid_amount: string;
    customer_note: string | null;
    internal_note: string | null;
    shipping_address: Record<string, string | null> | null;
    creator_name: string | null;
    items: OrderShowItem[];
    payments: OrderShowPayment[];
    allowed_transitions: OrderStatus[];
};

export type CustomerOrderItem = {
    id: string;
    order_number: string;
    status: OrderStatus;
    total_amount: string;
    currency_code: string;
    items_count: number;
    placed_at: string | null;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
};
