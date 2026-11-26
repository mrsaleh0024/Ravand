<?php

namespace App\Domain\Loyalty;

use App\Domain\Audit\AuditLogger;
use App\Domain\Sales\Money;
use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Loyalty points ledger service (phase 04, spec section 10 / phase 04).
 *
 * Contract (mirrors ADR-025 inventory ledger rules):
 * - The ledger is append-only; balances are always derived (Σ signed points),
 *   never stored on the customer (spec phase 04: derived from source data).
 * - Award/reverse run INSIDE the order lifecycle transaction opened by
 *   OrderService and are idempotent: the `order:{id}:earn` / `order:{id}:reverse`
 *   idempotency keys can each only ever produce one row per tenant.
 * - Points are awarded only at order completion, only while the tenant club
 *   is enabled, and only for a positive computed amount.
 * - Reversal links back to the earn row via `reversal_of`; reversing an order
 *   that never earned is a no-op.
 * - Manual adjustments require a reason and an authorized actor; they are
 *   audited (`loyalty.adjusted`).
 */
final class LoyaltyService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * The tenant's settings row, lazily created with safe defaults
     * (club disabled, 1,000,000 minor units per step, 1 point per step).
     */
    public function settingsFor(Tenant $tenant): LoyaltySetting
    {
        /** @var LoyaltySetting */
        return LoyaltySetting::query()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            ['points_enabled' => false, 'amount_step' => '1000000', 'points_per_step' => 1],
        );
    }

    /**
     * Points earned by a given amount under the tenant rule — integer math
     * only (bcmath floor division), never floats.
     */
    public function pointsForAmount(int|string $amount, ?LoyaltySetting $settings = null, ?string $tenantId = null): int
    {
        $settings ??= $this->findSettings($tenantId);

        if ($settings === null || ! $settings->points_enabled) {
            return 0;
        }

        $step = Money::normalize($settings->amount_step);

        if (Money::cmp($step, '0') <= 0) {
            return 0;
        }

        $steps = bcdiv(Money::normalize($amount), $step, 0);

        return max(0, (int) $steps) * $settings->points_per_step;
    }

    /**
     * Award points for a COMPLETED order. Idempotent: re-running the
     * completion event returns the already-recorded earn row and writes
     * nothing. Must be called inside the order transition transaction.
     */
    public function awardForOrder(Order $order): ?LoyaltyTransaction
    {
        $settings = $this->findSettings($order->tenant_id);

        if ($settings === null || ! $settings->points_enabled) {
            return null;
        }

        $points = $this->pointsForAmount($order->total_amount, $settings);

        if ($points <= 0) {
            return null;
        }

        return $this->append(
            order: $order,
            type: LoyaltyTransactionType::Earn,
            points: $points,
            idempotencyKey: 'order:'.$order->id.':earn',
            reason: null,
            reversalOf: null,
            amountBasis: $order->total_amount,
            actorId: null,
        );
    }

    /**
     * Fully reverse the points an order earned (cancel/return). Idempotent;
     * a no-op when the order never earned anything (e.g. cancelled before
     * completion). Must be called inside the order lifecycle transaction.
     */
    public function reverseForOrder(Order $order, string $reason): ?LoyaltyTransaction
    {
        /** @var LoyaltyTransaction|null $earn */
        $earn = LoyaltyTransaction::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('order_id', $order->id)
            ->where('transaction_type', LoyaltyTransactionType::Earn->value)
            ->first();

        if ($earn === null) {
            return null; // nothing was ever granted for this order
        }

        return $this->append(
            order: $order,
            type: LoyaltyTransactionType::Reverse,
            points: -1 * $earn->points,
            idempotencyKey: 'order:'.$order->id.':reverse',
            reason: $reason,
            reversalOf: $earn->id,
            amountBasis: $earn->amount_basis,
            actorId: null,
        );
    }

    /**
     * Manual adjustment — reason is mandatory and the actor is recorded on
     * the row and in the audit trail (spec phase 04).
     */
    public function adjust(Customer $customer, int $points, string $reason, User $actor): LoyaltyTransaction
    {
        $transaction = DB::transaction(function () use ($customer, $points, $reason, $actor): LoyaltyTransaction {
            /** @var LoyaltyTransaction $transaction */
            $transaction = LoyaltyTransaction::query()->create([
                'tenant_id' => $customer->tenant_id,
                'customer_id' => $customer->id,
                'order_id' => null,
                'idempotency_key' => null,
                'transaction_type' => LoyaltyTransactionType::Adjust->value,
                'points' => $points,
                'amount_basis' => null,
                'reason' => $reason,
                'reversal_of' => null,
                'created_by_user_id' => $actor->id,
            ]);

            return $transaction;
        });

        $this->audit->log(
            action: 'loyalty.adjusted',
            tenant: $this->context->tenant(),
            actor: $actor,
            entityType: 'customer',
            entityId: $customer->id,
            metadata: ['points' => $points, 'reason' => $reason],
        );

        return $transaction;
    }

    /** Current balance = Σ signed points of the ledger (never stored). */
    public function balanceFor(Customer $customer): int
    {
        return (int) LoyaltyTransaction::query()
            ->where('tenant_id', $customer->tenant_id)
            ->where('customer_id', $customer->id)
            ->sum('points');
    }

    /**
     * Loyalty summary for the customer 360° profile: balance plus the
     * earn/reverse/adjust totals — all derived, nothing cached.
     *
     * @return array{enabled: bool, balance: int, earned: int, reversed: int, adjusted: int, last_transaction_at: string|null}
     */
    public function summaryFor(Customer $customer): array
    {
        $rows = LoyaltyTransaction::query()
            ->where('tenant_id', $customer->tenant_id)
            ->where('customer_id', $customer->id)
            ->get(['transaction_type', 'points', 'created_at']);

        $settings = $this->findSettings($customer->tenant_id);

        return [
            'enabled' => (bool) ($settings?->points_enabled ?? false),
            'balance' => (int) $rows->sum('points'),
            'earned' => (int) $rows->where('transaction_type', LoyaltyTransactionType::Earn)->sum('points'),
            'reversed' => abs((int) $rows->where('transaction_type', LoyaltyTransactionType::Reverse)->sum('points')),
            'adjusted' => (int) $rows->where('transaction_type', LoyaltyTransactionType::Adjust)->sum('points'),
            'last_transaction_at' => $rows->max('created_at')?->toISOString(),
        ];
    }

    /**
     * Latest ledger rows for the profile tab.
     *
     * @return Collection<int, LoyaltyTransaction>
     */
    public function historyFor(Customer $customer, int $limit = 15): Collection
    {
        return LoyaltyTransaction::query()
            ->where('tenant_id', $customer->tenant_id)
            ->where('customer_id', $customer->id)
            ->with(['creator:id,name', 'order:id,order_number'])
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    // --- internals --------------------------------------------------------

    /** Settings row without creating one (a missing row means "disabled"). */
    private function findSettings(?string $tenantId = null): ?LoyaltySetting
    {
        $tenantId ??= $this->context->has() ? $this->context->tenant()->id : null;

        if ($tenantId === null) {
            return null;
        }

        /** @var LoyaltySetting|null */
        return LoyaltySetting::query()
            ->where('tenant_id', $tenantId)
            ->first();
    }

    /**
     * Idempotent append: an existing row for the same idempotency key is
     * returned untouched; otherwise the new ledger row is created.
     */
    private function append(
        Order $order,
        LoyaltyTransactionType $type,
        int $points,
        string $idempotencyKey,
        ?string $reason,
        ?string $reversalOf,
        ?string $amountBasis,
        ?string $actorId,
    ): ?LoyaltyTransaction {
        return DB::transaction(function () use ($order, $type, $points, $idempotencyKey, $reason, $reversalOf, $amountBasis, $actorId): ?LoyaltyTransaction {
            /** @var LoyaltyTransaction|null $existing */
            $existing = LoyaltyTransaction::query()
                ->where('tenant_id', $order->tenant_id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            /** @var LoyaltyTransaction $transaction */
            $transaction = LoyaltyTransaction::query()->create([
                'tenant_id' => $order->tenant_id,
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'idempotency_key' => $idempotencyKey,
                'transaction_type' => $type->value,
                'points' => $points,
                'amount_basis' => $amountBasis,
                'reason' => $reason,
                'reversal_of' => $reversalOf,
                'created_by_user_id' => $actorId,
            ]);

            return $transaction;
        });
    }
}
