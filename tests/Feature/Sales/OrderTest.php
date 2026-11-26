<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesTenancy;
use Tests\Concerns\SeedsCatalog;
use Tests\TestCase;

/**
 * Phase-03 order smoke checks (spec section 10, phase 03):
 * multi-item order with full payment, snapshot + total verification,
 * cancel with stock restore, over-purchase error, currency snapshot after
 * a tenant currency change, double-submit protection, tenant isolation.
 */
class OrderTest extends TestCase
{
    use CreatesTenancy, RefreshDatabase, SeedsCatalog;

    /** @return array{0: Customer, 1: string} customer id helper */
    private function makeCustomer($tenant, string $name = 'خانم رضایی'): Customer
    {
        return Customer::query()->create([
            'tenant_id' => $tenant->id,
            'customer_number' => 'CUS-'.str_pad((string) (Customer::query()->where('tenant_id', $tenant->id)->count() + 1), 6, '0', STR_PAD_LEFT),
            'display_name' => $name,
            'normalized_name' => $name,
            'customer_status' => 'active',
            'acquisition_channel' => 'instagram',
            'preferred_language' => 'fa',
        ]);
    }

    /** @return array<string, mixed> a valid orders.store payload */
    private function orderPayload(string $customerId, string $variantAId, string $variantBId, array $overrides = []): array
    {
        return array_merge([
            'idempotency_key' => 'test-'.uniqid(),
            'customer_id' => $customerId,
            'items' => [
                ['variant_id' => $variantAId, 'quantity' => 2, 'discount_amount' => '100000'],
                ['variant_id' => $variantBId, 'quantity' => 1, 'discount_amount' => '0'],
            ],
            'sales_channel' => 'instagram',
            'fulfillment_method' => 'in_person',
            'discount_amount' => '50000',
            'shipping_amount' => '60000',
            'payment' => [
                'amount' => '4610000',
                'method' => 'card_to_card',
                'idempotency_key' => 'pay-'.uniqid(),
            ],
        ], $overrides);
    }

    // -------------------------------------------------------------------------

    public function test_multi_item_order_with_full_payment_snapshots_and_totals(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه سفارش');
        $seed = $this->seedProduct($tenant);
        $this->addStock($owner, $seed['variantA'], 10);
        $this->addStock($owner, $seed['variantB'], 5);

        $customer = $this->makeCustomer($tenant);

        $response = $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('orders.store'), $this->orderPayload($customer->id, $seed['variantA']->id, $seed['variantB']->id));

        $response->assertRedirect(route('orders.show', ['order' => Order::query()->firstOrFail()->id]));

        $order = Order::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // Line math: (2×1,500,000 − 100,000) + (1×1,700,000) = 4,600,000
        $this->assertSame('4600000', $order->subtotal_amount);
        // total = subtotal − 50,000 + 60,000 = 4,610,000 (server-side only)
        $this->assertSame('4610000', $order->total_amount);
        $this->assertSame($tenant->refresh()->default_currency, $order->currency_code);
        $this->assertSame('ORD-000001', $order->order_number);
        $this->assertSame('confirmed', $order->status->value);

        // Snapshots (spec 5.5): name, attributes, sku, price at purchase time.
        $itemA = $order->items->firstWhere('product_variant_id', $seed['variantA']->id);

        $this->assertSame('چادر کرپ مدل ناز', $itemA->product_name_snapshot);
        $this->assertSame('CH-NAZ-BLK-M', $itemA->sku_snapshot);
        $this->assertSame('1500000', $itemA->unit_price);
        $this->assertSame('1100000', $itemA->cost_price_snapshot);
        $this->assertSame('مشکی', $itemA->variant_attributes_snapshot['color']['label']);

        // Full payment → paid.
        $this->assertCount(1, $order->payments);
        $this->assertSame('4610000', $order->payments->first()->amount);
        $this->assertSame($order->currency_code, $order->payments->first()->currency_code);

        // Stock left the shelf: 10−2 = 8 and 5−1 = 4.
        $this->assertSame(8, $seed['variantA']->refresh()->stock_on_hand);
        $this->assertSame(4, $seed['variantB']->refresh()->stock_on_hand);

        $saleOut = InventoryMovement::query()
            ->where('tenant_id', $tenant->id)
            ->where('movement_type', 'sale_out')
            ->get();

        $this->assertCount(2, $saleOut);
        $this->assertTrue($saleOut->every(fn ($m) => $m->reference_id === $order->id));
    }

    public function test_catalog_price_changes_never_rewrite_history(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه تاریخ');
        $seed = $this->seedProduct($tenant);
        $this->addStock($owner, $seed['variantA'], 5);
        $customer = $this->makeCustomer($tenant);

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('orders.store'), $this->orderPayload($customer->id, $seed['variantA']->id, $seed['variantB']->id, [
                'items' => [['variant_id' => $seed['variantA']->id, 'quantity' => 1]],
                'discount_amount' => '0',
                'shipping_amount' => '0',
                'payment' => ['amount' => '1500000', 'method' => 'cash', 'idempotency_key' => 'pay-hist-1'],
            ]));

        $order = Order::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // Change the catalog: new name, new price.
        $seed['variantA']->update(['sale_price' => '2000000']);
        $seed['product']->update(['name' => 'چادر جدید']);

        $item = $order->refresh()->items->first();

        $this->assertSame('1500000', $item->unit_price);
        $this->assertSame('چادر کرپ مدل ناز', $item->product_name_snapshot);
        $this->assertSame('1500000', $order->refresh()->total_amount);
    }

    public function test_purchasing_more_than_stock_fails_with_an_understandable_error(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه کمبود');
        $seed = $this->seedProduct($tenant);
        $this->addStock($owner, $seed['variantA'], 2);
        $customer = $this->makeCustomer($tenant);

        $response = $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('orders.store'), $this->orderPayload($customer->id, $seed['variantA']->id, $seed['variantB']->id, [
                'items' => [['variant_id' => $seed['variantA']->id, 'quantity' => 3]],
                'payment' => null,
            ]));

        $response->assertSessionHasErrors('order');
        $this->assertStringContainsString('کافی نیست', session('errors')->default->first('order'));

        // Nothing was persisted: no order, no stock change (single transaction).
        $this->assertSame(0, Order::query()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(2, $seed['variantA']->refresh()->stock_on_hand);
    }

    public function test_draft_orders_do_not_touch_stock_until_confirmed(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه پیش‌نویس');
        $seed = $this->seedProduct($tenant);
        $this->addStock($owner, $seed['variantA'], 5);
        $customer = $this->makeCustomer($tenant);

        // A draft with a payment is rejected outright.
        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('orders.store'), $this->orderPayload($customer->id, $seed['variantA']->id, $seed['variantB']->id, [
                'status' => 'draft',
                'payment' => ['amount' => '100', 'method' => 'cash'],
            ]))
            ->assertSessionHasErrors('payment');

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('orders.store'), $this->orderPayload($customer->id, $seed['variantA']->id, $seed['variantB']->id, [
                'status' => 'draft',
                'payment' => null,
                'items' => [['variant_id' => $seed['variantA']->id, 'quantity' => 2]],
            ]));

        $order = Order::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertSame('draft', $order->status->value);
        $this->assertSame(5, $seed['variantA']->refresh()->stock_on_hand, 'draft must not deduct stock');
        $this->assertSame(0, InventoryMovement::query()
            ->where('tenant_id', $tenant->id)
            ->where('movement_type', 'sale_out')
            ->count());

        // Confirming the draft is the moment stock leaves the shelf.
        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->patch(route('orders.status', ['order' => $order->id]), ['status' => 'confirmed'])
            ->assertRedirect();

        $this->assertSame(3, $seed['variantA']->refresh()->stock_on_hand);
        $this->assertSame('confirmed', $order->refresh()->status->value);
    }

    public function test_cancelling_restores_stock_via_reverse_movements(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه لغو');
        $seed = $this->seedProduct($tenant);
        $this->addStock($owner, $seed['variantA'], 10);
        $this->addStock($owner, $seed['variantB'], 5);
        $customer = $this->makeCustomer($tenant);

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('orders.store'), $this->orderPayload($customer->id, $seed['variantA']->id, $seed['variantB']->id));

        $order = Order::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $response = $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->patch(route('orders.cancel', ['order' => $order->id]), ['reason' => 'مشتری منصرف شد']);

        $response->assertRedirect()->assertSessionHas('success');

        // Stock is back and the ledger keeps BOTH directions (append-only).
        $this->assertSame(10, $seed['variantA']->refresh()->stock_on_hand);
        $this->assertSame(5, $seed['variantB']->refresh()->stock_on_hand);
        $this->assertSame('cancelled', $order->refresh()->status->value);

        $types = InventoryMovement::query()
            ->where('tenant_id', $tenant->id)
            ->pluck('movement_type')
            ->map(fn ($type) => $type->value)
            ->sort()
            ->values();

        $this->assertSame(
            ['cancellation_restore', 'cancellation_restore', 'opening', 'opening', 'sale_out', 'sale_out'],
            $types->all(),
        );
    }

    public function test_double_submit_creates_exactly_one_order(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه تکرار');
        $seed = $this->seedProduct($tenant);
        $this->addStock($owner, $seed['variantA'], 10);
        $this->addStock($owner, $seed['variantB'], 5);
        $customer = $this->makeCustomer($tenant);

        $payload = $this->orderPayload($customer->id, $seed['variantA']->id, $seed['variantB']->id);

        $this->actingAs($owner)->withCurrentTenant($owner, $tenant)->post(route('orders.store'), $payload);
        $second = $this->actingAs($owner)->withCurrentTenant($owner, $tenant)->post(route('orders.store'), $payload);

        $second->assertRedirect();

        $this->assertSame(1, Order::query()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(1, Payment::query()->where('tenant_id', $tenant->id)->count());

        // Stock deducted exactly once.
        $this->assertSame(8, $seed['variantA']->refresh()->stock_on_hand);
        $this->assertSame(4, $seed['variantB']->refresh()->stock_on_hand);
    }

    public function test_currency_snapshot_survives_a_tenant_currency_change(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه ارز');

        // Explicit starting point: prices entered in rial.
        $tenant->update(['default_currency' => 'IRR']);

        $seed = $this->seedProduct($tenant);
        $this->addStock($owner, $seed['variantA'], 5);
        $customer = $this->makeCustomer($tenant);

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('orders.store'), $this->orderPayload($customer->id, $seed['variantA']->id, $seed['variantB']->id, [
                'items' => [['variant_id' => $seed['variantA']->id, 'quantity' => 1]],
                'payment' => ['amount' => '1500000', 'method' => 'cash', 'idempotency_key' => 'pay-cur-1'],
            ]));

        $firstOrder = Order::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('IRR', $firstOrder->currency_code);

        // The shop switches to toman.
        $tenant->update(['default_currency' => 'IRT']);

        // History keeps its snapshot…
        $this->assertSame('IRR', $firstOrder->refresh()->currency_code);
        $this->assertSame('IRR', $firstOrder->payments->first()->currency_code);

        // …and a new sale against IRR-priced variants is blocked with a
        // clear message until prices are re-entered in the new currency.
        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('orders.store'), $this->orderPayload($customer->id, $seed['variantA']->id, $seed['variantB']->id, [
                'items' => [['variant_id' => $seed['variantA']->id, 'quantity' => 1]],
                'payment' => null,
                'idempotency_key' => 'second-currency-attempt',
            ]))
            ->assertSessionHasErrors('order');

        // After re-pricing in IRT, the new order snapshots IRT.
        $seed['variantA']->update(['sale_price' => '150000', 'currency_code' => 'IRT']);

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('orders.store'), $this->orderPayload($customer->id, $seed['variantA']->id, $seed['variantB']->id, [
                'items' => [['variant_id' => $seed['variantA']->id, 'quantity' => 1]],
                'payment' => null,
                'idempotency_key' => 'second-currency-ok',
            ]));

        $secondOrder = Order::query()->where('tenant_id', $tenant->id)->where('id', '!=', $firstOrder->id)->firstOrFail();

        $this->assertSame('IRT', $secondOrder->currency_code);
    }

    public function test_order_history_shows_on_the_customer_profile(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه پروفایل');
        $seed = $this->seedProduct($tenant);
        $this->addStock($owner, $seed['variantA'], 5);
        $customer = $this->makeCustomer($tenant);

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('orders.store'), $this->orderPayload($customer->id, $seed['variantA']->id, $seed['variantB']->id, [
                'items' => [['variant_id' => $seed['variantA']->id, 'quantity' => 1]],
                'payment' => null,
            ]));

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->get(route('customers.show', ['customer' => $customer->id]))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('customers/Show')
                    ->where('ordersTotal', 1)
                    ->has('orders', 1)
                    ->where('orders.0.order_number', 'ORD-000001'),
            );
    }

    public function test_orders_are_tenant_isolated(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه الف');
        [$ownerB, $tenantB] = $this->createOwner('فروشگاه ب');
        $seed = $this->seedProduct($tenant);
        $this->addStock($owner, $seed['variantA'], 5);
        $customer = $this->makeCustomer($tenant);

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('orders.store'), $this->orderPayload($customer->id, $seed['variantA']->id, $seed['variantB']->id, [
                'items' => [['variant_id' => $seed['variantA']->id, 'quantity' => 1]],
                'payment' => null,
            ]));

        $order = Order::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // Not in tenant B's list…
        $this->actingAs($ownerB)
            ->withCurrentTenant($ownerB, $tenantB)
            ->get(route('orders.index'))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));

        // …and the id alone grants nothing (spec 2.2).
        $this->actingAs($ownerB)
            ->withCurrentTenant($ownerB, $tenantB)
            ->get(route('orders.show', ['order' => $order->id]))
            ->assertNotFound();

        $this->actingAs($ownerB)
            ->withCurrentTenant($ownerB, $tenantB)
            ->patch(route('orders.cancel', ['order' => $order->id]))
            ->assertNotFound();
    }

    public function test_operator_records_sales_but_cannot_cancel(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه نقش');
        $seed = $this->seedProduct($tenant);
        $this->addStock($owner, $seed['variantA'], 5);
        $customer = $this->makeCustomer($tenant);

        $operator = User::factory()->create();
        $this->attachMembership($operator, $tenant, 'operator');

        // Creating an order + taking the payment is operator work.
        $this->actingAs($operator)
            ->withCurrentTenant($operator, $tenant)
            ->post(route('orders.store'), $this->orderPayload($customer->id, $seed['variantA']->id, $seed['variantB']->id, [
                'items' => [['variant_id' => $seed['variantA']->id, 'quantity' => 1]],
                'payment' => ['amount' => '1500000', 'method' => 'cash', 'idempotency_key' => 'pay-op-1'],
            ]))
            ->assertRedirect();

        $order = Order::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // Money/stock-moving decisions are owner/manager territory.
        $this->actingAs($operator)
            ->withCurrentTenant($operator, $tenant)
            ->patch(route('orders.cancel', ['order' => $order->id]))
            ->assertForbidden();

        $this->assertSame('confirmed', $order->refresh()->status->value);
    }

    public function test_payment_status_lifecycle_and_idempotent_payments(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه پرداخت');
        $seed = $this->seedProduct($tenant);
        $this->addStock($owner, $seed['variantA'], 5);
        $customer = $this->makeCustomer($tenant);

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('orders.store'), $this->orderPayload($customer->id, $seed['variantA']->id, $seed['variantB']->id, [
                'items' => [['variant_id' => $seed['variantA']->id, 'quantity' => 1]],
                'discount_amount' => '0',
                'shipping_amount' => '0',
                'payment' => null, // credit sale first
            ]));

        $order = Order::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // Unpaid right after creation.
        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->get(route('orders.show', ['order' => $order->id]))
            ->assertInertia(fn (Assert $page) => $page->where('order.payment_status', 'unpaid'));

        // Partial payment → partially_paid.
        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('order-payments.store', ['order' => $order->id]), [
                'amount' => '700000',
                'method' => 'cash',
                'idempotency_key' => 'pay-part-1',
            ]);

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->get(route('orders.show', ['order' => $order->id]))
            ->assertInertia(fn (Assert $page) => $page->where('order.payment_status', 'partially_paid'));

        // The same payment key submitted twice → one row.
        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('order-payments.store', ['order' => $order->id]), [
                'amount' => '700000',
                'method' => 'cash',
                'idempotency_key' => 'pay-part-1',
            ]);

        $this->assertSame(1, Payment::query()->where('tenant_id', $tenant->id)->count());

        // Remaining balance completes the order → paid.
        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('order-payments.store', ['order' => $order->id]), [
                'amount' => '800000',
                'method' => 'card_to_card',
                'idempotency_key' => 'pay-rest-1',
            ]);

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->get(route('orders.show', ['order' => $order->id]))
            ->assertInertia(fn (Assert $page) => $page->where('order.payment_status', 'paid'));

        // Refund both payments (owner/manager only) → refunded.
        Payment::query()->where('tenant_id', $tenant->id)->get()->each(
            fn (Payment $payment) => $this->actingAs($owner)
                ->withCurrentTenant($owner, $tenant)
                ->patch(route('order-payments.refund', ['order' => $order->id, 'payment' => $payment->id]))
                ->assertRedirect(),
        );

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->get(route('orders.show', ['order' => $order->id]))
            ->assertInertia(fn (Assert $page) => $page->where('order.payment_status', 'refunded'));
    }

    public function test_full_return_after_delivery_restores_stock(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه مرجوعی');
        $seed = $this->seedProduct($tenant);
        $this->addStock($owner, $seed['variantA'], 5);
        $customer = $this->makeCustomer($tenant);

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('orders.store'), $this->orderPayload($customer->id, $seed['variantA']->id, $seed['variantB']->id, [
                'items' => [['variant_id' => $seed['variantA']->id, 'quantity' => 2]],
                'payment' => null,
            ]));

        $order = Order::query()->where('tenant_id', $tenant->id)->firstOrFail();

        foreach (['processing', 'shipped', 'delivered'] as $status) {
            $this->actingAs($owner)
                ->withCurrentTenant($owner, $tenant)
                ->patch(route('orders.status', ['order' => $order->id]), ['status' => $status])
                ->assertRedirect();
        }

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->patch(route('orders.return', ['order' => $order->id]), ['note' => 'مرجوعی کامل'])
            ->assertRedirect();

        $order->refresh();

        $this->assertSame('returned', $order->status->value);
        $this->assertNotNull($order->returned_at);
        $this->assertSame('مرجوعی کامل', $order->return_note);
        $this->assertSame(5, $seed['variantA']->refresh()->stock_on_hand, 'return_in must restore stock');

        $this->assertDatabaseHas('inventory_movements', [
            'tenant_id' => $tenant->id,
            'movement_type' => 'return_in',
            'quantity_delta' => 2,
        ]);
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه چرخه');
        $seed = $this->seedProduct($tenant);
        $customer = $this->makeCustomer($tenant);

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('orders.store'), $this->orderPayload($customer->id, $seed['variantA']->id, $seed['variantB']->id, [
                'items' => [['variant_id' => $seed['variantA']->id, 'quantity' => 1]],
                'payment' => null,
                'status' => 'draft',
            ]));

        $order = Order::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // draft → completed is not a legal jump.
        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->patch(route('orders.status', ['order' => $order->id]), ['status' => 'completed'])
            ->assertSessionHasErrors('status');

        // A delivered→cancelled shortcut must go through return instead.
        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->patch(route('orders.status', ['order' => $order->id]), ['status' => 'confirmed'])
            ->assertRedirect();

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->patch(route('orders.cancel', ['order' => $order->refresh()->id]))
            ->assertRedirect();

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->patch(route('orders.status', ['order' => $order->id]), ['status' => 'confirmed'])
            ->assertSessionHasErrors('status');
    }

    public function test_shipping_orders_need_an_address(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه پست');
        $seed = $this->seedProduct($tenant);
        $this->addStock($owner, $seed['variantA'], 5);
        $customer = $this->makeCustomer($tenant);

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('orders.store'), $this->orderPayload($customer->id, $seed['variantA']->id, $seed['variantB']->id, [
                'fulfillment_method' => 'shipping',
                'shipping_address' => null,
                'payment' => null,
                'items' => [['variant_id' => $seed['variantA']->id, 'quantity' => 1]],
            ]))
            ->assertSessionHasErrors('shipping_address.address_line');

        // With an address it goes through and the snapshot is kept.
        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('orders.store'), $this->orderPayload($customer->id, $seed['variantA']->id, $seed['variantB']->id, [
                'fulfillment_method' => 'shipping',
                'shipping_address' => [
                    'recipient_name' => 'خانم رضایی',
                    'phone' => '09120000000',
                    'province' => 'تهران',
                    'city' => 'تهران',
                    'address_line' => 'خیابان ولیعصر، پلاک ۱۲',
                    'postal_code' => '1234567890',
                ],
                'payment' => null,
                'items' => [['variant_id' => $seed['variantA']->id, 'quantity' => 1]],
            ]))
            ->assertRedirect();

        $order = Order::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertSame('خیابان ولیعصر، پلاک ۱۲', $order->shipping_address_snapshot['address_line']);
    }
}
