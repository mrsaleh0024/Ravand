<?php

namespace App\Domain\Sales;

/**
 * Derived payment status of an order (spec 5.5): computed from the recorded
 * payments, never stored as editable state.
 */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Refunded = 'refunded';
}
