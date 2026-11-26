<?php

namespace App\Domain\Sales;

use App\Domain\Tenancy\TenantContext;
use App\Models\Order;

/**
 * Tenant-scoped display numbers: ORD-000001, ORD-000002, ... (spec 5.5).
 *
 * Same locking scheme as CustomerNumberGenerator: call next() inside the
 * order-creation transaction so two concurrent submissions can never take
 * the same number.
 */
final class OrderNumberGenerator
{
    private const PREFIX = 'ORD-';

    private const PAD = 6;

    public function __construct(private readonly TenantContext $context) {}

    /** Next free display number for the current tenant. Call inside a transaction. */
    public function next(): string
    {
        $tenantId = $this->context->tenant()->id;

        $max = Order::query()
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->max('order_number');

        $next = $this->sequenceOf((string) $max) + 1;

        return self::PREFIX.str_pad((string) $next, self::PAD, '0', STR_PAD_LEFT);
    }

    /** Extract the numeric part of an ORD-xxxxxx value (0 for none). */
    private function sequenceOf(string $number): int
    {
        if (! str_starts_with($number, self::PREFIX)) {
            return 0;
        }

        return (int) substr($number, strlen(self::PREFIX));
    }
}
