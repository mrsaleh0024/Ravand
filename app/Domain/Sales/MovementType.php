<?php

namespace App\Domain\Sales;

/**
 * Inventory movement kinds (spec 5.4) — the ledger is append-only.
 */
enum MovementType: string
{
    case Opening = 'opening';
    case PurchaseIn = 'purchase_in';
    case SaleOut = 'sale_out';
    case ReturnIn = 'return_in';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case CancellationRestore = 'cancellation_restore';

    /** Movements that increase stock when recorded with a positive delta. */
    public function isInbound(): bool
    {
        return in_array($this, [self::Opening, self::PurchaseIn, self::ReturnIn, self::AdjustmentIn, self::CancellationRestore], true);
    }

    /** Manual types a panel user may pick in the inventory form. */
    public static function manualChoices(): array
    {
        return [self::Opening, self::PurchaseIn, self::AdjustmentIn, self::AdjustmentOut];
    }
}
