<?php

namespace App\Domain\Sales;

/**
 * Thrown when a movement would push a variant's stock below zero
 * (spec 5.4: negative stock is forbidden by default). The message is the
 * user-facing Persian error surfaced by the controller as 422.
 */
final class InsufficientStockException extends SalesException
{
    public static function forVariant(string $variantLabel, int $available, int $requested): self
    {
        return new self(
            sprintf('موجودی «%s» کافی نیست. موجودی فعلی: %d — درخواستی: %d', $variantLabel, $available, $requested)
        );
    }
}
