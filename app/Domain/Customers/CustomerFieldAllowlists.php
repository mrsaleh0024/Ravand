<?php

namespace App\Domain\Customers;

/**
 * Stable English codes for categorical customer fields (spec 4.2).
 *
 * Persian display labels live ONLY in the UI dictionary
 * (resources/js/lib/i18n.ts), never in the database. These allowlists are
 * the single validation source; adding a value means adding it here plus a
 * UI label — nothing else.
 */
final class CustomerFieldAllowlists
{
    public const GENDERS = ['female', 'male', 'other', 'not_specified'];

    public const ACQUISITION_CHANNELS = [
        'instagram', 'whatsapp', 'telegram', 'referral',
        'in_person', 'advertisement', 'other', 'unknown',
    ];

    public const CONTACT_TYPES = ['mobile', 'landline', 'email', 'other'];

    public const SOCIAL_PLATFORMS = ['instagram', 'telegram', 'whatsapp', 'eitaa', 'bale', 'rubika', 'other'];

    public const CONSENT_CHANNELS = ['sms', 'whatsapp', 'instagram', 'telegram', 'email', 'phone'];

    public const CONSENT_PURPOSES = ['transactional', 'marketing', 'service'];

    public const CONSENT_STATUSES = ['granted', 'denied', 'withdrawn', 'unknown'];

    public const CONSENT_CAPTURE_METHODS = ['manual', 'form', 'message', 'other'];

    public const PREFERENCE_CATEGORIES = [
        'product_category', 'color', 'fabric', 'style', 'occasion',
        'price_sensitivity', 'budget_level', 'brand', 'other',
    ];

    public const PREFERENCE_LEVELS = ['like', 'dislike', 'neutral'];

    public const PREFERENCE_SOURCES = ['customer_stated', 'purchase_inferred', 'staff_observed'];

    public const MEASUREMENT_SOURCES = ['customer_stated', 'staff_measured', 'purchase_inferred'];

    public const NOTE_VISIBILITIES = ['private', 'team'];

    public const INTERACTION_TYPES = [
        'message', 'call', 'follow_up', 'complaint',
        'question', 'purchase_related', 'other',
    ];

    public const INTERACTION_CHANNELS = ['instagram', 'whatsapp', 'telegram', 'eitaa', 'bale', 'rubika', 'phone', 'in_person', 'other'];

    public const INTERACTION_OUTCOMES = [
        'purchase', 'no_purchase', 'waiting', 'resolved', 'unresolved', 'other',
    ];

    public const FOLLOW_UP_STATUSES = ['pending', 'done', 'cancelled'];

    /** Non-instantiable — constants only. */
    private function __construct() {}
}
