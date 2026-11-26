/** Customer CRM types (phase 02, spec 5.2). */

export type CustomerStatus = 'active' | 'inactive' | 'blocked' | 'archived';

export type CustomerListItem = {
    id: string;
    customer_number: string;
    display_name: string;
    phone: string | null;
    city: string | null;
    status: CustomerStatus;
    channel: string;
    tags: TagItem[];
    created_at: string | null;
};

export type TagItem = {
    id: string;
    name: string;
    slug: string;
    color: string | null;
};

export type CustomerContactItem = {
    id: string;
    type: string;
    label: string | null;
    value: string;
    is_primary: boolean;
    is_verified: boolean;
};

export type CustomerSocialItem = {
    id: string;
    platform: string;
    username: string;
    is_primary: boolean;
};

export type CustomerAddressItem = {
    id: string;
    title: string;
    city: string | null;
    province: string | null;
    address_line: string | null;
    postal_code: string | null;
    recipient_name: string | null;
    is_default: boolean;
};

export type CustomerConsentItem = {
    id: string;
    channel: string;
    purpose: string;
    status: string;
    captured_at: string | null;
};

export type CustomerPreferenceItem = {
    id: string;
    category: string;
    value_code: string;
    value_label: string;
    level: string | null;
    source: string;
};

export type CustomerMeasurementItem = {
    id: string;
    code: string;
    value_text: string | null;
    value_number: string | null;
    unit: string | null;
    recorded_at: string | null;
    source: string;
};

export type CustomerNoteItem = {
    id: string;
    body: string;
    visibility: 'private' | 'team';
    author: string | null;
    created_at: string | null;
};

export type CustomerInteractionItem = {
    id: string;
    type: string;
    channel: string;
    subject: string | null;
    summary: string;
    outcome: string | null;
    occurred_at: string | null;
    follow_up_at: string | null;
    follow_up_status: string | null;
    author: string | null;
};

export type CustomerProfile = {
    id: string;
    customer_number: string;
    display_name: string;
    first_name: string | null;
    last_name: string | null;
    gender: string | null;
    birth_date: string | null;
    status: CustomerStatus;
    acquisition_channel: string;
    acquisition_note: string | null;
    preferred_contact_channel: string | null;
    preferred_language: string | null;
    created_at: string | null;
    contacts: CustomerContactItem[];
    social_accounts: CustomerSocialItem[];
    addresses: CustomerAddressItem[];
    consents: CustomerConsentItem[];
    preferences: CustomerPreferenceItem[];
    measurements: CustomerMeasurementItem[];
    tags: TagItem[];
};

export type DuplicateMatch = {
    id: string;
    customer_number: string;
    display_name: string;
    phone: string | null;
    status: CustomerStatus;
};
