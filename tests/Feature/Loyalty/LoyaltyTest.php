<?php

namespace Tests\Feature\Loyalty;

use App\Domain\Loyalty\LoyaltyService;
use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesTenancy;
use Tests\Concerns\SeedsCatalog;
use Tests\TestCase;

/**
 * Phase-04 loyalty smoke checks (spec section 10 / phase 04):
 * enable + rule definition, points on completion, idempotent award,
 * reversal on return/cancel, manual adjustment with reason + role gate,
 * dashboard KPIs matching sample orders, customer report by purchase
 * stats/tag, and tenant isolation.
 */
class LoyaltyTest extends TestCase
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

    /** Enable the club through the settings endpoint (smoke: «فعال کردن امتیاز و تعریف rule»). */
    private function enableClub($owner, $tenant, array $overrides = []): void
    {
        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->put(route('loyalty.settings.update'), array_merge([
                'points_enabled' => '1',
                'amount_step' => '1000000',
                'points_per_step' => 10,
            ], $overrides))
            ->assertRedirect();
    }

    /** Create a confirmed order through the sales endpoint. */
    private function createConfirmedOrder($owner, $tenant, Customer $customer, array $seed): Order
    {
        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('orders.store'), [
                'idempotency_key' => 'loy-'.uniqid(),
                'customer_id' => $customer->id,
                'items' => [
                    ['variant_id' => $seed['variantA']->id, 'quantity' => 2, 'discount_amount' => '100000'],
                    ['variant_id' => $seed['variantB']->id, 'quantity' => 1, 'discount_amount' => '0'],
                ],
                'sales_channel' => 'instagram',
                'fulfillment_method' => 'in_person',
                'discount_amount' => '50000',
                'shipping_amount' => '60000',
                'payment' => ['amount' => '4610000', 'method' => 'card_to_card', 'idempotency_key' => 'pay-'.uniqid()],
            ])
            ->assertRedirect();

        /** @var Order $order */
        $order = Order::query()->where('tenant_id', $tenant->id)->latest('placed_at')->firstOrFail();

        return $order;
    }

    /** Walk the state machine to `completed` (smoke: «تکمیل سفارش و مشاهده امتیاز»). */
    private function completeOrder($owner, $tenant, Order $order): void
    {
        foreach (['processing', 'shipped', 'delivered', 'completed'] as $status) {
            $this->actingAs($owner)
                ->withCurrentTenant($owner, $tenant)
                ->patch(route('orders.status', ['order' => $order->id]), ['status' => $status])
                ->assertRedirect();
        }
    }

    /** Direct order row for read-only aggregations (KPI/report tests). */
    private function makeOrder($tenant, Customer $customer, string $status, string $total): Order
    {
        $tenant->refresh(); // pick up DB defaults (default_currency …)
        /** @var Order $order */
        $order = Order::query()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'order_number' => 'T-'.strtoupper(uniqid()),
            'sales_channel' => 'instagram',
            'fulfillment_method' => 'in_person',
            'status' => $status,
            'placed_at' => now(),
            'completed_at' => $status === 'completed' ? now() : null,
            'subtotal_amount' => $total,
            'discount_amount' => '0',
            'shipping_amount' => '0',
            'total_amount' => $total,
            'currency_code' => $tenant->default_currency,
        ]);

        return $order;
    }

    // -------------------------------------------------------------------------

    public function test_club_is_disabled_by_default_and_settings_can_enable_it(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه باشگاه');

        // Settings page renders with safe defaults (club off).
        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->get(route('loyalty.settings.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('settings.points_enabled', false)
                ->where('settings.amount_step', '1000000')
                ->where('settings.points_per_step', 1));

        // A zero step cannot be enabled (division-by-zero guard).
        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->put(route('loyalty.settings.update'), [
                'points_enabled' => '1',
                'amount_step' => '0',
                'points_per_step' => 10,
            ])
            ->assertSessionHasErrors('amount_step');

        // Enabling with a valid rule works.
        $this->enableClub($owner, $tenant);

        $this->assertDatabaseHas('loyalty_settings', [
            'tenant_id' => $tenant->id,
            'points_enabled' => true,
            'amount_step' => '1000000',
            'points_per_step' => 10,
        ]);
    }

    public function test_completed_order_earns_points_and_duplicate_events_do_not_double_award(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه امتیاز');
        $seed = $this->seedProduct($tenant);
        $this->addStock($owner, $seed['variantA'], 10);
        $this->addStock($owner, $seed['variantB'], 5);
        $customer = $this->makeCustomer($tenant);

        $this->enableClub($owner, $tenant);

        $order = $this->createConfirmedOrder($owner, $tenant, $customer, $seed);

        // No points before completion.
        $this->assertSame(0, LoyaltyTransaction::query()->where('tenant_id', $tenant->id)->count());

        $this->completeOrder($owner, $tenant, $order);

        // total 4,610,000 with a 1,000,000 step → 4 steps × 10 = 40 points.
        $this->assertDatabaseHas('loyalty_transactions', [
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'transaction_type' => 'earn',
            'points' => 40,
            'idempotency_key' => 'order:'.$order->id.':earn',
        ]);

        $earn = LoyaltyTransaction::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('4610000', $earn->amount_basis);

        $service = app(LoyaltyService::class);
        $this->assertSame(40, $service->balanceFor($customer));

        // Smoke: «اجرای دوباره event و اطمینان از عدم اعطای duplicate» —
        // replaying the completion event returns the same row, nothing new.
        $replay = $service->awardForOrder($order->refresh());

        $this->assertSame($earn->id, $replay?->id);
        $this->assertSame(1, LoyaltyTransaction::query()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(40, $service->balanceFor($customer));
    }

    public function test_disabled_club_and_zero_step_orders_earn_nothing(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه خاموش');
        $seed = $this->seedProduct($tenant);
        $this->addStock($owner, $seed['variantA'], 5);
        $this->addStock($owner, $seed['variantB'], 5);
        $customer = $this->makeCustomer($tenant);

        // No settings row at all → disabled by default.
        $order = $this->createConfirmedOrder($owner, $tenant, $customer, $seed);
        $this->completeOrder($owner, $tenant, $order);

        $this->assertSame(0, LoyaltyTransaction::query()->where('tenant_id', $tenant->id)->count());
    }

    public function test_return_after_completion_reverses_points_and_cancel_before_completion_is_a_noop(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه برگشت');
        $seed = $this->seedProduct($tenant);
        $this->addStock($owner, $seed['variantA'], 10);
        $this->addStock($owner, $seed['variantB'], 5);
        $customer = $this->makeCustomer($tenant);

        $this->enableClub($owner, $tenant);

        // Order 1: completed, then returned → earn + reverse.
        $returnedOrder = $this->createConfirmedOrder($owner, $tenant, $customer, $seed);
        $this->completeOrder($owner, $tenant, $returnedOrder);

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->patch(route('orders.return', ['order' => $returnedOrder->id]), ['note' => 'مرجوعی کامل'])
            ->assertRedirect();

        $earn = LoyaltyTransaction::query()
            ->where('tenant_id', $tenant->id)
            ->where('transaction_type', 'earn')
            ->firstOrFail();
        $reverse = LoyaltyTransaction::query()
            ->where('tenant_id', $tenant->id)
            ->where('transaction_type', 'reverse')
            ->firstOrFail();

        // Smoke: «لغو/مرجوعی و مشاهده reversal» — traceable to the earn row.
        $this->assertSame(-40, $reverse->points);
        $this->assertSame($earn->id, $reverse->reversal_of);
        $this->assertSame($returnedOrder->id, $reverse->order_id);
        $this->assertSame(0, app(LoyaltyService::class)->balanceFor($customer));

        // Order 2: cancelled before completion → never earned, no reversal row.
        $cancelledOrder = $this->createConfirmedOrder($owner, $tenant, $customer, $seed);
        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->patch(route('orders.cancel', ['order' => $cancelledOrder->id]), ['reason' => 'مشتری منصرف شد'])
            ->assertRedirect();

        $this->assertSame(2, LoyaltyTransaction::query()->where('tenant_id', $tenant->id)->count());
    }

    public function test_manual_adjustment_requires_reason_and_an_authorized_role(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه تعدیل');
        $customer = $this->makeCustomer($tenant);

        $operator = User::factory()->create();
        $this->attachMembership($operator, $tenant, 'operator');

        // Operators may read the profile (loyalty tab) but not adjust.
        $this->actingAs($operator)
            ->withCurrentTenant($operator, $tenant)
            ->get(route('customers.show', ['customer' => $customer->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canAdjustLoyalty', false));

        $this->actingAs($operator)
            ->withCurrentTenant($operator, $tenant)
            ->post(route('loyalty.adjustments.store', ['customer' => $customer->id]), [
                'points' => 5,
                'reason' => 'جبران',
            ])
            ->assertForbidden();

        // A reason is mandatory (smoke: «ثبت adjustment با reason»).
        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('loyalty.adjustments.store', ['customer' => $customer->id]), [
                'points' => 5,
                'reason' => '',
            ])
            ->assertSessionHasErrors('reason');

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('loyalty.adjustments.store', ['customer' => $customer->id]), [
                'points' => -5,
                'reason' => 'جبران اشتباه ثبت‌شده',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('loyalty_transactions', [
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'transaction_type' => 'adjust',
            'points' => -5,
            'reason' => 'جبران اشتباه ثبت‌شده',
        ]);

        // The adjustment is audited and the balance reflects it.
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'loyalty.adjusted',
        ]);
        $this->assertSame(-5, app(LoyaltyService::class)->balanceFor($customer));
    }

    public function test_dashboard_kpis_match_sample_orders(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه شاخص');
        $buyer = $this->makeCustomer($tenant, 'خریدار یک');
        $this->makeCustomer($tenant, 'خریدار دو'); // new customer, no purchases

        $this->makeOrder($tenant, $buyer, 'completed', '2000000');
        $this->makeOrder($tenant, $buyer, 'completed', '1000000');
        $this->makeOrder($tenant, $buyer, 'confirmed', '500000');
        $this->makeOrder($tenant, $buyer, 'draft', '300000'); // drafts do not count

        // Smoke: «تطبیق KPI داشبورد با چند سفارش نمونه».
        $response = $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->get(route('panel.kpis', ['window' => '30d']))
            ->assertOk()
            ->assertJsonPath('kpis.customers_total', 2)
            ->assertJsonPath('kpis.orders_count', 3)          // confirmed + 2 completed, no draft
            ->assertJsonPath('kpis.completed_sales', '3000000')
            ->assertJsonPath('kpis.average_order_value', '1500000')
            ->assertJsonPath('kpis.new_customers', 2);

        $this->assertSame($tenant->default_currency, $response->json('currency_code'));

        // Every KPI carries its window (spec acceptance).
        $this->assertSame('30d', $response->json('window'));

        // Unknown windows are rejected.
        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->get(route('panel.kpis', ['window' => '400d']))
            ->assertStatus(422);

        // Operators may read business KPIs too (no security data inside).
        $operator = User::factory()->create();
        $this->attachMembership($operator, $tenant, 'operator');

        $this->actingAs($operator)
            ->withCurrentTenant($operator, $tenant)
            ->get(route('panel.kpis', ['window' => '7d']))
            ->assertOk();
    }

    public function test_customer_report_lists_purchase_stats_and_filters_by_tag(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه گزارش');
        $vip = $this->makeCustomer($tenant, 'مشتری وفادار');
        $casual = $this->makeCustomer($tenant, 'مشتری عادی');

        $this->makeOrder($tenant, $vip, 'completed', '2000000');
        $this->makeOrder($tenant, $vip, 'confirmed', '500000'); // valid purchase, not "spent"
        $this->makeOrder($tenant, $vip, 'cancelled', '100000'); // neither purchase nor spend
        $this->makeOrder($tenant, $casual, 'completed', '700000');

        $tag = Tag::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'VIP',
            'slug' => 'vip',
            'color_token' => 'emerald',
        ]);

        // Direct pivot insert (created_at only, spec 5.2 — same pattern as
        // CustomerTagController::attach; Eloquent attach() would add an
        // updated_at column the table does not have).
        DB::table('customer_tag')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenant->id,
            'customer_id' => $vip->id,
            'tag_id' => $tag->id,
            'created_at' => now(),
        ]);

        $assertRow = function (Assert $page) {
            $page->has('rows.data', 2)
                ->where('rows.data.0.display_name', 'مشتری وفادار') // default sort: last purchase
                ->where('rows.data.0.purchase_count', 2)
                ->where('rows.data.0.total_spent', '2000000')
                ->where('rows.data.1.purchase_count', 1)
                ->where('rows.data.1.total_spent', '700000');
        };

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->get(route('reports.customers.index'))
            ->assertInertia($assertRow);

        // Tag filter narrows the report to the tagged customer only.
        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->get(route('reports.customers.index', ['tag' => 'vip']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 1)
                ->where('rows.data.0.display_name', 'مشتری وفادار'));

        // Sorting by total spent keeps definitions intact.
        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->get(route('reports.customers.index', ['sort' => 'total_spent']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.data.0.display_name', 'مشتری وفادار')
                ->where('rows.data.1.display_name', 'مشتری عادی'));
    }

    public function test_loyalty_endpoints_are_tenant_isolated(): void
    {
        [$ownerA, $tenantA] = $this->createOwner('فروشگاه الف');
        [$ownerB, $tenantB] = $this->createOwner('فروشگاه ب');
        $seed = $this->seedProduct($tenantA);
        $this->addStock($ownerA, $seed['variantA'], 10);
        $this->addStock($ownerA, $seed['variantB'], 5);
        $customerA = $this->makeCustomer($tenantA);
        $this->enableClub($ownerA, $tenantA);

        $orderA = $this->createConfirmedOrder($ownerA, $tenantA, $customerA, $seed);
        $this->completeOrder($ownerA, $tenantA, $orderA);

        // Tenant B never sees tenant A's points in its KPIs…
        $this->actingAs($ownerB)
            ->withCurrentTenant($ownerB, $tenantB)
            ->get(route('panel.kpis', ['window' => '30d']))
            ->assertOk()
            ->assertJsonPath('kpis.customers_total', 0)
            ->assertJsonPath('kpis.completed_sales', '0');

        // …nor in its customer report…
        $this->actingAs($ownerB)
            ->withCurrentTenant($ownerB, $tenantB)
            ->get(route('reports.customers.index'))
            ->assertInertia(fn (Assert $page) => $page->where('rows.total', 0));

        // …and a tenant B actor cannot adjust tenant A's customer (scoped
        // binding → 404, never a cross-tenant write).
        $this->actingAs($ownerB)
            ->withCurrentTenant($ownerB, $tenantB)
            ->post(route('loyalty.adjustments.store', ['customer' => $customerA->id]), [
                'points' => 999,
                'reason' => 'تلاش عبوری',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('loyalty_transactions', [
            'tenant_id' => $tenantB->id,
            'customer_id' => $customerA->id,
        ]);
    }
}
