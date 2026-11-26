<?php

namespace App\Domain\Sales;

/**
 * Order lifecycle states (spec 5.5).
 *
 * draft         — recorded but not final; does NOT touch inventory.
 * confirmed     — sale is real: stock deducted at this point (spec 5.4).
 * processing    — being prepared.
 * shipped       — handed to the courier / customer left with the goods.
 * delivered     — customer received the order.
 * completed     — final happy state; loyalty points will hook in here (phase 04).
 * cancelled     — cancelled before delivery; a cancellation_restore movement
 *                 returns stock (append-only, the sale_out row stays).
 * returned      — full-order return after delivery; return_in movement.
 */
enum OrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Returned = 'returned';

    /** Allowed forward transitions; terminal states have none. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Processing, self::Shipped, self::Cancelled],
            self::Processing => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered],
            self::Delivered => [self::Completed, self::Returned],
            self::Completed => [self::Returned],
            self::Cancelled, self::Returned => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** States whose items already left stock (a cancel must restore it). */
    public function hasDeductedInventory(): bool
    {
        return ! in_array($this, [self::Draft, self::Cancelled], true);
    }

    /** Whether payments may be recorded against this order. */
    public function acceptsPayments(): bool
    {
        return ! in_array($this, [self::Draft, self::Cancelled], true);
    }
}
