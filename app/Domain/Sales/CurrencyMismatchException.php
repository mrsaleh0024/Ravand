<?php

namespace App\Domain\Sales;

/**
 * Thrown when an order would mix a variant priced in a different tenant
 * currency (e.g. prices still in IRR after the shop switched to IRT).
 * The order currency is snapshotted from the tenant setting, so all item
 * prices must share it (spec 5.5 / ADR-006).
 */
final class CurrencyMismatchException extends SalesException
{
    public static function forVariant(string $variantLabel, string $variantCurrency, string $orderCurrency): self
    {
        return new self(
            sprintf('قیمت «%s» با ارز فروشگاه هماهنگ نیست (قیمت به %s، ارز فروشگاه %s). ابتدا قیمت‌ها را به‌روز کنید.', $variantLabel, $variantCurrency, $orderCurrency)
        );
    }
}
