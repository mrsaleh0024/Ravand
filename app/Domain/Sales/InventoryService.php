<?php

namespace App\Domain\Sales;

use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Append-only inventory ledger writer (spec 5.4).
 *
 * Contract:
 * - Must run inside a DB transaction (the caller opens it).
 * - The variant row is locked FOR UPDATE before any check, so concurrent
 *   order submissions serialize on the stock check.
 * - The movement's idempotency_key is checked first: a retried operation
 *   returns the already-recorded movement and touches nothing.
 * - stock_on_hand on the variant is a CACHE maintained in the same
 *   transaction; inventory_movements remains the source of truth.
 * - Negative stock is rejected with InsufficientStockException.
 */
final class InventoryService
{
    /**
     * Record one movement and update the cached stock. Returns the recorded
     * movement, or the previously recorded one when the idempotency key was
     * already used (no new row, no stock change).
     *
     * @param  array{reference_type?: string|null, reference_id?: string|null, reason?: string|null, occurred_at?: Carbon|null}  $context
     */
    public function record(
        ProductVariant $variant,
        MovementType $type,
        int $quantityDelta,
        ?string $idempotencyKey,
        User $actor,
        array $context = [],
    ): InventoryMovement {
        $tenantId = $variant->tenant_id;

        return DB::transaction(function () use ($variant, $type, $quantityDelta, $idempotencyKey, $actor, $context, $tenantId): InventoryMovement {
            if ($idempotencyKey !== null) {
                /** @var InventoryMovement|null $existing */
                $existing = InventoryMovement::query()
                    ->where('tenant_id', $tenantId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            /** @var ProductVariant $locked */
            $locked = ProductVariant::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $variant->id)
                ->lockForUpdate()
                ->firstOrFail();

            $newStock = $locked->stock_on_hand + $quantityDelta;

            if ($newStock < 0) {
                $label = $locked->variant_name ?: ($locked->sku ?: 'کالا');

                throw InsufficientStockException::forVariant($label, $locked->stock_on_hand, abs($quantityDelta));
            }

            /** @var InventoryMovement $movement */
            $movement = InventoryMovement::query()->create([
                'tenant_id' => $tenantId,
                'product_variant_id' => $locked->id,
                'idempotency_key' => $idempotencyKey,
                'movement_type' => $type->value,
                'quantity_delta' => $quantityDelta,
                'reference_type' => $context['reference_type'] ?? null,
                'reference_id' => $context['reference_id'] ?? null,
                'reason' => $context['reason'] ?? null,
                'occurred_at' => $context['occurred_at'] ?? now(),
                'created_by_user_id' => $actor->id,
            ]);

            $locked->forceFill(['stock_on_hand' => $newStock])->save();

            return $movement;
        });
    }
}
