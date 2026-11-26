<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Sales\Money;
use App\Domain\Sales\OrderStatus;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Business KPIs for the dashboard (phase 04, spec section 10 / phase 04).
 *
 * Every number is derived straight from the source tables on each request —
 * no caching layer (spec phase 04: «محاسبات مشتق‌شده از source data؛ cache
 * فقط در صورت ضرورت»). The queries are cheap COUNT/aggregates on indexed
 * columns, polled at most every 10 seconds like the live panel (ADR-023).
 *
 * KPI definitions (also shown in the UI, spec: each KPI must carry an
 * explicit definition and time window):
 * - customers_total  — non-archived customers right now (no window).
 * - orders_count     — non-draft orders PLACED in the window (placed_at).
 * - completed_sales  — Σ total of orders COMPLETED in the window (completed_at).
 * - average_order_value — completed_sales ÷ number of those orders (integer).
 * - new_customers    — customers CREATED in the window.
 */
class KpiController extends Controller
{
    /** Allowed windows in days; the default is the last 30 days. */
    private const WINDOWS = ['7d' => 7, '30d' => 30, '90d' => 90];

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $window = (string) $request->query('window', '30d');

        abort_unless(array_key_exists($window, self::WINDOWS), 422);

        $since = now()->subDays(self::WINDOWS[$window]);

        $customersTotal = Customer::query()
            ->where('customer_status', '!=', 'archived')
            ->count();

        $newCustomers = Customer::query()
            ->where('created_at', '>=', $since)
            ->count();

        $ordersCount = Order::query()
            ->where('placed_at', '>=', $since)
            ->where('status', '!=', OrderStatus::Draft->value)
            ->count();

        // Completed sales: exact bcmath sum over completed orders in window.
        $completedTotals = Order::query()
            ->where('status', OrderStatus::Completed->value)
            ->where('completed_at', '>=', $since)
            ->pluck('total_amount');

        $completedCount = $completedTotals->count();
        $completedSales = $completedTotals->reduce(
            fn (string $carry, string $total): string => Money::add($carry, $total),
            '0',
        );

        $averageOrderValue = $completedCount > 0
            ? bcdiv($completedSales, (string) $completedCount, 0)
            : null;

        return response()->json([
            'window' => $window,
            'currency_code' => $this->context->tenant()->default_currency,
            'kpis' => [
                'customers_total' => $customersTotal,
                'orders_count' => $ordersCount,
                'completed_sales' => $completedSales,
                'average_order_value' => $averageOrderValue,
                'new_customers' => $newCustomers,
            ],
            'generated_at' => now()->toISOString(),
        ])->header('Cache-Control', 'no-store');
    }
}
