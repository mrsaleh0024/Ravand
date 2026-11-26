<?php

namespace App\Domain\Sales;

/**
 * Lifecycle of a single payment row (spec 5.5): recorded money either
 * stays recorded, is refunded to the customer, or is voided (bookkeeping
 * mistake — never silently edited or deleted).
 */
enum PaymentRecordStatus: string
{
    case Recorded = 'recorded';
    case Refunded = 'refunded';
    case Voided = 'voided';
}
