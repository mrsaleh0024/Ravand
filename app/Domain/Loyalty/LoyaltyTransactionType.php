<?php

namespace App\Domain\Loyalty;

/**
 * Loyalty ledger row kinds (phase 04, spec section 10).
 *
 * earn    — points granted exactly once, when an order completes.
 * reverse — full reversal of an order's earn row (cancel/return after
 *           completion); carries `reversal_of` back to the original.
 * adjust  — manual correction by an authorized role; always carries a reason.
 */
enum LoyaltyTransactionType: string
{
    case Earn = 'earn';
    case Reverse = 'reverse';
    case Adjust = 'adjust';
}
