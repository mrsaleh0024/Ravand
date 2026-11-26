<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Customers\CustomerStatus;
use App\Domain\Sales\Money;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer report (phase 04, spec section 10 / phase 04): customers by last
 * purchase, purchase count, total spent and tag.
 *
 * Documented definitions (ADR-026 — the spec only requires that the basis
 * be documented and consistent):
 * - «خرید معتبر» (valid purchase) = an order that is not a draft and not
 *   cancelled. It feeds `purchase_count` and `last_purchase_at` — a returned
 *   order still counts as a purchase occasion.
 * - `total_spent` = Σ total of COMPLETED orders only — the strictest honest
 *   definition of money actually kept.
 */
class CustomerReportController extends Controller
{
    private const PAGE_SIZE = 20;

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function index(Request $request): Response
    {
        $tenant = $this->context->tenant();
        $tagSlug = (string) $request->query('tag', '');
        $sort = (string) $request->query('sort', 'last_purchase');

        if (! in_array($sort, ['last_purchase', 'purchase_count', 'total_spent', 'name'], true)) {
            $sort = 'last_purchase';
        }

        // Subqueries are built on the base table with an explicit tenant_id:
        // they aggregate exactly this tenant's orders and nothing else.
        $validOrders = DB::table('orders')
            ->select('customer_id')
            ->selectRaw('count(*) as purchase_count, max(placed_at) as last_purchase_at')
            ->where('tenant_id', $tenant->id)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->groupBy('customer_id');

        $completedOrders = DB::table('orders')
            ->select('customer_id')
            ->selectRaw('sum(total_amount) as total_spent')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'completed')
            ->groupBy('customer_id');

        $query = Customer::query()
            ->where('customer_status', '!=', CustomerStatus::Archived->value)
            ->select('customers.*')
            ->selectRaw('coalesce(valid_orders.purchase_count, 0) as purchase_count')
            ->selectRaw('completed_orders.total_spent as total_spent')
            ->selectRaw('valid_orders.last_purchase_at as last_purchase_at')
            ->leftJoinSub($validOrders, 'valid_orders', fn ($join) => $join
                ->on('customers.id', '=', 'valid_orders.customer_id'))
            ->leftJoinSub($completedOrders, 'completed_orders', fn ($join) => $join
                ->on('customers.id', '=', 'completed_orders.customer_id'))
            ->with(['primaryContact', 'tags'])
            ->withCasts(['last_purchase_at' => 'datetime']);

        if ($tagSlug !== '') {
            $query->whereHas('tags', fn ($q) => $q->where('slug', $tagSlug));
        }

        match ($sort) {
            'purchase_count' => $query->orderByDesc('purchase_count'),
            'total_spent' => $query->orderByDesc('total_spent'),
            'name' => $query->orderBy('normalized_name'),
            default => $query->orderByDesc('last_purchase_at'),
        };

        // Secondary key keeps the order deterministic for equal aggregates.
        $page = $query
            ->orderByDesc('customers.created_at')
            ->paginate(self::PAGE_SIZE)
            ->withQueryString();

        return Inertia::render('reports/Customers', [
            'rows' => $page->through(fn (Customer $customer): array => $this->row($customer)),
            'filters' => ['tag' => $tagSlug, 'sort' => $sort],
            'tags' => $this->tagOptions(),
            'currency' => $tenant->default_currency,
        ]);
    }

    // --- internals --------------------------------------------------------

    /** @return array<string, mixed> */
    private function row(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'customer_number' => $customer->customer_number,
            'display_name' => $customer->display_name,
            'phone' => $customer->primaryContact?->display_value,
            'tags' => $customer->tags->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'color' => $tag->color_token,
            ])->all(),
            'purchase_count' => (int) ($customer->purchase_count ?? 0),
            'total_spent' => Money::normalize($customer->total_spent ?? 0),
            'last_purchase_at' => $customer->last_purchase_at?->toISOString(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function tagOptions(): array
    {
        return Tag::query()
            ->where('tenant_id', $this->context->tenant()->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'color' => $tag->color_token,
            ])
            ->all();
    }
}
