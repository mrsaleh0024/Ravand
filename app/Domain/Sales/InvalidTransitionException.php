<?php

namespace App\Domain\Sales;

/**
 * Thrown when an order status change is not in the allowed transition map
 * (see OrderStatus::allowedTransitions).
 */
final class InvalidTransitionException extends SalesException
{
    public static function fromTo(OrderStatus $from, OrderStatus $to): self
    {
        return new self(
            sprintf('تغییر وضعیت سفارش از «%s» به «%s» مجاز نیست.', $from->value, $to->value)
        );
    }
}
