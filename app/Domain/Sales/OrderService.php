<?php

namespace App\Domain\Sales;

use App\Domain\Audit\AuditLogger;
use App\Domain\Loyalty\LoyaltyService;
use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Order lifecycle service (phase 03, spec 5.4/5.5).
 *
 * Financial invariants enforced here — never in the browser:
 * - Totals are computed server-side: subtotal = Σ(qty × price − line
 *   discount); total = subtotal − order discount + shipping (bcmath).
 * - Items snapshot name/attributes/sku/price/cost at purchase time.
 * - The order currency is snapshotted from the tenant setting; every
 *   variant price must already use that currency.
 * - Stock is deducted exactly once, at confirmation, with row locks; a
 *   retry with the same idempotency key returns the existing order.
 * - Cancel/return append reverse movements; nothing is ever deleted.
 */
final class OrderService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
        private readonly OrderNumberGenerator $numbers,
        private readonly InventoryService $inventory,
        private readonly LoyaltyService $loyalty,
    ) {}

    /**
     * Create an order (and optionally its first payment) atomically.
     *
     * @param  array{
     *     customer_id: string,
     *     items: array<int, array{variant_id: string, quantity: int, discount_amount: string|int|null}>,
     *     sales_channel: string,
     *     fulfillment_method: string,
     *     status: 'draft'|'confirmed',
     *     discount_amount: string|int|null,
     *     shipping_amount: string|int|null,
     *     customer_note?: string|null,
     *     internal_note?: string|null,
     *     shipping_address?: array<string, mixed>|null,
     *     payment?: array{amount: string|int, method: string, paid_at?: string|null, reference_code?: string|null, note?: string|null, idempotency_key?: string|null}|null,
     * }  $data
     */
    public function create(Tenant $tenant, User $actor, array $data): Order
    {
        $order = DB::transaction(function () use ($tenant, $actor, $data): Order {
            // 1. Double-submit / retry guard (spec 5.5): the same key can
            //    only ever produce one order inside this tenant.
            $idempotencyKey = $data['idempotency_key'] ?? null;

            if ($idempotencyKey !== null) {
                /** @var Order|null $existing */
                $existing = Order::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            // 2. Resolve customer + variants within the current tenant.
            /** @var Customer $customer */
            $customer = Customer::query()->findOrFail($data['customer_id']);

            $variantIds = array_values(array_unique(array_column($data['items'], 'variant_id')));
            /** @var Collection<string, ProductVariant> $variants */
            $variants = ProductVariant::query()
                ->with('product')
                ->whereIn('id', $variantIds)
                ->get()
                ->keyBy('id');

            $currency = $tenant->default_currency;
            $status = OrderStatus::from($data['status']);

            // 3. Snapshot lines and compute totals with integer math only.
            $lines = [];
            $subtotal = '0';

            foreach ($data['items'] as $line) {
                $variant = $variants->get($line['variant_id']);

                if ($variant === null || ! $variant->is_active) {
                    throw new InactiveVariantException($line['variant_id']);
                }

                if ($variant->currency_code !== $currency) {
                    $label = $variant->variant_name ?: ($variant->sku ?: $variant->product->name);

                    throw CurrencyMismatchException::forVariant($label, $variant->currency_code, $currency);
                }

                $quantity = max(1, (int) $line['quantity']);
                $unitPrice = Money::normalize($variant->sale_price);
                $gross = Money::mul($unitPrice, $quantity);
                $lineDiscount = Money::min(Money::normalize($line['discount_amount'] ?? 0), $gross);
                $lineTotal = Money::sub($gross, $lineDiscount);
                $subtotal = Money::add($subtotal, $lineTotal);

                $lines[] = [
                    'variant' => $variant,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $lineDiscount,
                    'line_total' => $lineTotal,
                ];
            }

            $discount = Money::min(Money::normalize($data['discount_amount'] ?? 0), $subtotal);
            $shipping = Money::normalize($data['shipping_amount'] ?? 0);
            $total = Money::add(Money::sub($subtotal, $discount), $shipping);

            // 4. Persist the order + snapshotted items.
            /** @var Order $order */
            $order = Order::query()->create([
                'tenant_id' => $tenant->id,
                'customer_id' => $customer->id,
                'idempotency_key' => $idempotencyKey,
                'order_number' => $this->numbers->next(),
                'sales_channel' => $data['sales_channel'],
                'fulfillment_method' => $data['fulfillment_method'],
                'status' => $status->value,
                'placed_at' => now(),
                'subtotal_amount' => $subtotal,
                'discount_amount' => $discount,
                'shipping_amount' => $shipping,
                'total_amount' => $total,
                'currency_code' => $currency,
                'customer_note' => $data['customer_note'] ?? null,
                'internal_note' => $data['internal_note'] ?? null,
                'shipping_address_snapshot' => $data['shipping_address'] ?? null,
                'created_by_user_id' => $actor->id,
            ]);

            foreach ($lines as $line) {
                /** @var ProductVariant $variant */
                $variant = $line['variant'];

                OrderItem::query()->create([
                    'tenant_id' => $tenant->id,
                    'order_id' => $order->id,
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'product_name_snapshot' => $variant->product->name,
                    'variant_name_snapshot' => $variant->variant_name,
                    'variant_attributes_snapshot' => $variant->attributes,
                    'sku_snapshot' => $variant->sku,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount_amount' => $line['discount_amount'],
                    'line_total' => $line['line_total'],
                    'cost_price_snapshot' => $variant->cost_price,
                ]);
            }

            // 5. Deduct stock at confirmation — never for drafts (spec 5.4).
            if ($status === OrderStatus::Confirmed) {
                $this->deductStock($order, $lines, $actor);
            }

            // 6. Optional first payment (default in the UI: pay in full).
            if (($data['payment'] ?? null) !== null && $status->acceptsPayments()) {
                $this->createPayment($order, $actor, $data['payment']);
            }

            return $order->load(['items', 'payments', 'customer']);
        });

        $this->audit->log(
            action: $order->wasRecentlyCreated ? 'order.created' : 'order.duplicate_submit_ignored',
            tenant: $tenant,
            actor: $actor,
            entityType: 'order',
            entityId: $order->id,
            metadata: [
                'order_number' => $order->order_number,
                'customer_id' => $order->customer_id,
                'status' => $order->status->value,
                'total_amount' => $order->total_amount,
                'currency_code' => $order->currency_code,
                'items_count' => $order->items->count(),
            ],
        );

        return $order;
    }

    /**
     * Cancel an order before delivery: appends cancellation_restore
     * movements for everything that left stock; no movement is deleted.
     */
    public function cancel(Order $order, User $actor, ?string $reason = null): Order
    {
        $order = DB::transaction(function () use ($order, $actor): Order {
            $from = $order->status;

            if (! $from->canTransitionTo(OrderStatus::Cancelled)) {
                throw InvalidTransitionException::fromTo($from, OrderStatus::Cancelled);
            }

            if ($from->hasDeductedInventory()) {
                $this->restoreStock($order, $actor, MovementType::CancellationRestore);
            }

            $order->forceFill(['status' => OrderStatus::Cancelled->value])->save();

            // Cancelling before delivery normally happens before completion,
            // so this is a no-op unless points were already granted.
            $this->loyalty->reverseForOrder($order, 'لغو سفارش');

            return $order;
        });

        $this->audit->log(
            action: 'order.cancelled',
            tenant: $this->context->tenant(),
            actor: $actor,
            entityType: 'order',
            entityId: $order->id,
            metadata: [
                'order_number' => $order->order_number,
                'reason' => $reason,
            ],
        );

        return $order;
    }

    /** Full-order return after delivery (spec 5.4: return_in movement). */
    public function markReturned(Order $order, User $actor, ?string $note = null): Order
    {
        $order = DB::transaction(function () use ($order, $actor, $note): Order {
            $from = $order->status;

            if (! $from->canTransitionTo(OrderStatus::Returned)) {
                throw InvalidTransitionException::fromTo($from, OrderStatus::Returned);
            }

            $this->restoreStock($order, $actor, MovementType::ReturnIn);

            $order->forceFill([
                'status' => OrderStatus::Returned->value,
                'returned_at' => now(),
                'return_note' => $note,
            ])->save();

            // A completed order that comes back reverses its earned points
            // (phase 04); returns from `delivered` never earned anything.
            $this->loyalty->reverseForOrder($order, 'مرجوعی سفارش');

            return $order;
        });

        $this->audit->log(
            action: 'order.returned',
            tenant: $this->context->tenant(),
            actor: $actor,
            entityType: 'order',
            entityId: $order->id,
            metadata: ['order_number' => $order->order_number, 'note' => $note],
        );

        return $order;
    }

    /** Generic status transition (processing/shipped/delivered/completed…). */
    public function transition(Order $order, User $actor, OrderStatus $to): Order
    {
        DB::transaction(function () use ($order, $actor, $to): void {
            $from = $order->status;

            if (! $from->canTransitionTo($to)) {
                throw InvalidTransitionException::fromTo($from, $to);
            }

            // Confirming a draft is the moment stock leaves the shelf.
            if ($from === OrderStatus::Draft && $to === OrderStatus::Confirmed) {
                $this->deductStock($order, $this->resolveLines($order), $actor);
            }

            $order->forceFill([
                'status' => $to->value,
                'completed_at' => $to === OrderStatus::Completed ? now() : $order->completed_at,
            ])->save();

            // Loyalty points are granted exactly once, at completion
            // (phase 04, spec section 10) — idempotent inside this transaction.
            if ($to === OrderStatus::Completed) {
                $this->loyalty->awardForOrder($order);
            }
        });

        $this->audit->log(
            action: 'order.status_changed',
            tenant: $this->context->tenant(),
            actor: $actor,
            entityType: 'order',
            entityId: $order->id,
            metadata: ['order_number' => $order->order_number, 'to' => $to->value],
        );

        return $order;
    }

    /**
     * @param  array{amount: string|int, method: string, paid_at?: string|null, reference_code?: string|null, note?: string|null, idempotency_key?: string|null}  $data
     */
    public function recordPayment(Order $order, User $actor, array $data): Payment
    {
        if (! $order->status->acceptsPayments()) {
            throw new SalesException('برای سفارش در این وضعیت نمی‌توان پرداخت ثبت کرد.');
        }

        $payment = $this->createPayment($order, $actor, $data);

        $this->audit->log(
            action: 'payment.recorded',
            tenant: $this->context->tenant(),
            actor: $actor,
            entityType: 'payment',
            entityId: $payment->id,
            metadata: [
                'order_number' => $order->order_number,
                'amount' => $payment->amount,
                'currency_code' => $payment->currency_code,
                'method' => $payment->payment_method,
            ],
        );

        return $payment;
    }

    public function refundPayment(Payment $payment, User $actor): Payment
    {
        return $this->closePayment($payment, $actor, PaymentRecordStatus::Refunded, 'payment.refunded');
    }

    public function voidPayment(Payment $payment, User $actor): Payment
    {
        return $this->closePayment($payment, $actor, PaymentRecordStatus::Voided, 'payment.voided');
    }

    /**
     * Derived payment status (spec 5.5): sum of recorded (non-refunded,
     * non-voided) payments versus the order total.
     */
    public function paymentStatus(Order $order): PaymentStatus
    {
        $order->loadMissing('payments');

        $payments = $order->payments;

        $recorded = '0';
        $refunded = '0';

        foreach ($payments as $payment) {
            if ($payment->status === PaymentRecordStatus::Recorded) {
                $recorded = Money::add($recorded, $payment->amount);
            } elseif ($payment->status === PaymentRecordStatus::Refunded) {
                $refunded = Money::add($refunded, $payment->amount);
            }
        }

        $total = Money::normalize($order->total_amount);

        if (Money::cmp($total, '0') <= 0) {
            return PaymentStatus::Paid;
        }

        if (Money::cmp($recorded, $total) >= 0) {
            return PaymentStatus::Paid;
        }

        if (Money::cmp($recorded, '0') > 0) {
            return PaymentStatus::PartiallyPaid;
        }

        if (Money::cmp($refunded, '0') > 0) {
            return PaymentStatus::Refunded;
        }

        return PaymentStatus::Unpaid;
    }

    // --- internals --------------------------------------------------------

    /** @param  array{amount: string|int, method: string, paid_at?: string|null, reference_code?: string|null, note?: string|null, idempotency_key?: string|null}  $data */
    private function createPayment(Order $order, User $actor, array $data): Payment
    {
        return DB::transaction(function () use ($order, $actor, $data): Payment {
            $clientKey = $data['idempotency_key'] ?? null;
            $idempotencyKey = 'order:'.$order->id.':pay:'.($clientKey ?: (string) Str::ulid());

            /** @var Payment|null $existing */
            $existing = Payment::query()
                ->where('tenant_id', $order->tenant_id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            /** @var Payment $payment */
            $payment = Payment::query()->create([
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'idempotency_key' => $idempotencyKey,
                'amount' => Money::normalize($data['amount']),
                'currency_code' => $order->currency_code,
                'payment_method' => $data['method'],
                'status' => PaymentRecordStatus::Recorded->value,
                'paid_at' => $data['paid_at'] ?? now(),
                'reference_code' => $data['reference_code'] ?? null,
                'note' => $data['note'] ?? null,
                'created_by_user_id' => $actor->id,
            ]);

            return $payment;
        });
    }

    private function closePayment(Payment $payment, User $actor, PaymentRecordStatus $to, string $action): Payment
    {
        if ($payment->status !== PaymentRecordStatus::Recorded) {
            throw new SalesException('این پرداخت قبلاً بسته شده است.');
        }

        $payment->forceFill(['status' => $to->value])->save();

        $this->audit->log(
            action: $action,
            tenant: $this->context->tenant(),
            actor: $actor,
            entityType: 'payment',
            entityId: $payment->id,
            metadata: ['order_id' => $payment->order_id, 'amount' => $payment->amount],
        );

        return $payment;
    }

    /**
     * @param  array<int, array{variant: ProductVariant, quantity: int, discount_amount: string, unit_price: string, line_total: string}>  $lines
     */
    private function deductStock(Order $order, array $lines, User $actor): void
    {
        foreach ($lines as $line) {
            $variant = $line['variant'];

            if (! $variant->product->track_inventory) {
                continue;
            }

            $this->inventory->record(
                variant: $variant,
                type: MovementType::SaleOut,
                quantityDelta: -1 * $line['quantity'],
                idempotencyKey: 'order:'.$order->id.':sale_out:'.$variant->id,
                actor: $actor,
                context: ['reference_type' => 'order', 'reference_id' => $order->id],
            );
        }
    }

    /** Append positive movements for every tracked item of the order. */
    private function restoreStock(Order $order, User $actor, MovementType $type): void
    {
        $order->loadMissing(['items.variant.product', 'items.product']);

        foreach ($order->items as $item) {
            $variant = $item->variant;

            if ($variant === null) {
                continue;
            }

            $tracks = ($item->product?->track_inventory) ?? ($variant->product?->track_inventory) ?? false;

            if (! $tracks) {
                continue;
            }

            $key = 'order:'.$order->id.':'.($type === MovementType::ReturnIn ? 'return_in' : 'cancel').':'.$variant->id;

            $this->inventory->record(
                variant: $variant,
                type: $type,
                quantityDelta: $item->quantity,
                idempotencyKey: $key,
                actor: $actor,
                context: ['reference_type' => 'order', 'reference_id' => $order->id],
            );
        }
    }

    /** Resolve stored items back into deductible lines (draft → confirmed). */
    private function resolveLines(Order $order): array
    {
        $order->loadMissing(['items.variant.product']);

        $lines = [];

        foreach ($order->items as $item) {
            if ($item->variant === null) {
                continue;
            }

            $lines[] = [
                'variant' => $item->variant,
                'quantity' => $item->quantity,
                'unit_price' => Money::normalize($item->unit_price),
                'discount_amount' => Money::normalize($item->discount_amount),
                'line_total' => Money::normalize($item->line_total),
            ];
        }

        return $lines;
    }
}
