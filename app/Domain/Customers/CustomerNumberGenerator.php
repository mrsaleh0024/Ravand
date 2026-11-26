<?php

namespace App\Domain\Customers;

use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;

/**
 * Tenant-scoped display numbers: CUS-000001, CUS-000002, ... (spec 4.1).
 *
 * Generated inside the same transaction as the customer insert with a row
 * lock on the current maximum, so two concurrent creations can never take
 * the same number. Padding grows past six digits naturally.
 */
final class CustomerNumberGenerator
{
    private const PREFIX = 'CUS-';

    private const PAD = 6;

    public function __construct(private readonly TenantContext $context) {}

    /** Next free display number for the current tenant. Call inside a transaction. */
    public function next(): string
    {
        $tenantId = $this->context->tenant()->id;

        $max = Customer::query()
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->max('customer_number');

        $next = $this->sequenceOf((string) $max) + 1;

        return self::PREFIX.str_pad((string) $next, self::PAD, '0', STR_PAD_LEFT);
    }

    /** Extract the numeric part of a CUS-xxxxxx value (0 for none). */
    private function sequenceOf(string $number): int
    {
        if (! str_starts_with($number, self::PREFIX)) {
            return 0;
        }

        return (int) substr($number, strlen(self::PREFIX));
    }
}
