<?php

namespace App\Domain\Sales;

/**
 * Thrown when an order references a variant that does not exist in the
 * current tenant or is deactivated — the customer-facing catalog must not
 * sell dead rows (spec 5.3).
 */
final class InactiveVariantException extends SalesException
{
    public function __construct(string $variantId)
    {
        parent::__construct('تنوع انتخاب‌شده دیگر فعال نیست یا یافت نشد. فهرست را به‌روز کنید.');
    }
}
