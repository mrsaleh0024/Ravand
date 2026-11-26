<?php

namespace App\Http\Controllers\Sales;

use App\Domain\Sales\Money;
use App\Domain\Sales\OrderService;
use App\Domain\Sales\OrderStatus;
use App\Domain\Sales\SalesChannel;
use App\Domain\Sales\SalesException;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\PersianText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manual order entry and lifecycle (phase 03, spec 5.5 / 6.7).
 *
 * The form is optimized for a phone screen: one customer, a few lines,
 * one full payment by default. Every amount shown to the user is
 * recomputed by OrderService on the server — the browser total is a
 * preview, never an input.
 */
class OrderController extends Controller
{
    private const PAGE_SIZE = 20;

    public function __construct(
        private readonly TenantContext $context,
        private readonly OrderService $orders,
    ) {}

    /** Order list with server-side search/filters (spec 6.7). */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $channel = (string) $request->query('channel', '');

        $query = Order::query()->with(['customer:id,display_name,customer_number', 'items']);

        if ($status !== '' && in_array($status, array_column(OrderStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        if ($channel !== '' && in_array($channel, array_column(SalesChannel::cases(), 'value'), true)) {
            $query->where('sales_channel', $channel);
        }

        if ($search !== '') {
            $digits = PersianText::digitsOnly($search);

            if ($digits !== '') {
                // «ORD-000001» and friend codes contain ASCII digits only.
                $query->where(function ($q) use ($digits): void {
                    $q->where('order_number', 'like', "%{$digits}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('customer_number', 'like', "%{$digits}%"));
                });
            } else {
                $normalized = PersianText::normalize($search);
                $query->whereHas('customer', fn ($c) => $c->where('normalized_name', 'like', "%{$normalized}%"));
            }
        }

        $page = $query
            ->orderByDesc('placed_at')
            ->paginate(self::PAGE_SIZE)
            ->withQueryString();

        return Inertia::render('orders/Index', [
            'orders' => $page->through(fn (Order $order): array => $this->listRow($order)),
            'filters' => ['search' => $search, 'status' => $status, 'channel' => $channel],
            'statuses' => array_column(OrderStatus::cases(), 'value'),
            'channels' => array_column(SalesChannel::cases(), 'value'),
        ]);
    }

    /** Mobile-friendly manual order form (spec 6.7). */
    public function create(Request $request): Response
    {
        $tenant = $this->context->tenant();

        $products = Product::query()
            ->where('is_active', true)
            ->with(['variants' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        $preselected = null;
        $customerId = (string) $request->query('customer', '');

        if ($customerId !== '') {
            /** @var Customer|null $customer */
            $customer = Customer::query()
                ->with(['contacts' => fn ($q) => $q->orderByDesc('is_primary'), 'addresses'])
                ->find($customerId);

            if ($customer !== null) {
                $isPrivileged = in_array($this->context->role(), ['owner', 'manager'], true);
                $phone = $customer->contacts->first(fn ($c): bool => $c->contact_type === 'mobile')
                    ?? $customer->contacts->first();

                $preselected = [
                    'id' => $customer->id,
                    'display_name' => $customer->display_name,
                    'customer_number' => $customer->customer_number,
                    'phone' => $phone?->display_value,
                    'addresses' => $customer->addresses->map(fn ($a): array => [
                        'id' => $a->id,
                        'title' => $a->title,
                        'city' => $a->city,
                        'province' => $a->province,
                        'address_line' => $isPrivileged ? $a->address_line : null,
                        'postal_code' => $isPrivileged ? $a->postal_code : null,
                        'recipient_name' => $a->recipient_name,
                        'is_default' => $a->is_default,
                    ])->all(),
                ];
            }
        }

        return Inertia::render('orders/Create', [
            'products' => $products->map(fn (Product $product): array => $this->productOption($product))->all(),
            'currency' => $tenant->default_currency,
            'channels' => array_column(SalesChannel::cases(), 'value'),
            'preselectedCustomer' => $preselected,
        ]);
    }

    /** Register the sale; totals and stock are server-side only. */
    public function store(Request $request): RedirectResponse
    {
        $this->normalizeNumericInputs($request);

        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:80'],
            'customer_id' => [
                'required', 'string', 'max:26',
                Rule::exists('customers', 'id')->where('tenant_id', $this->context->tenant()->id),
            ],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.variant_id' => ['required', 'string', 'max:26'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.discount_amount' => ['nullable', 'string', 'max:30', 'regex:/^[\d۰-۹٠-٩,،\s]+$/'],
            'sales_channel' => ['required', 'string', Rule::in(array_column(SalesChannel::cases(), 'value'))],
            'fulfillment_method' => ['required', 'string', 'in:shipping,pickup,in_person'],
            'status' => ['nullable', 'string', 'in:draft,confirmed'],
            'discount_amount' => ['nullable', 'string', 'max:30', 'regex:/^[\d۰-۹٠-٩,،\s]+$/'],
            'shipping_amount' => ['nullable', 'string', 'max:30', 'regex:/^[\d۰-۹٠-٩,،\s]+$/'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
            'shipping_address' => ['nullable', 'array'],
            'shipping_address.recipient_name' => ['nullable', 'string', 'max:120'],
            'shipping_address.phone' => ['nullable', 'string', 'max:30'],
            'shipping_address.province' => ['nullable', 'string', 'max:60'],
            'shipping_address.city' => ['nullable', 'string', 'max:60'],
            'shipping_address.address_line' => ['nullable', 'string', 'max:500'],
            'shipping_address.postal_code' => ['nullable', 'string', 'max:20'],
            'payment' => ['nullable', 'array'],
            'payment.amount' => ['required_with:payment', 'string', 'regex:/^\d+$/', 'not_in:0', 'max:20'],
            'payment.method' => ['required_with:payment', 'string', 'in:cash,card_to_card,pos,online,bank_transfer,other'],
            'payment.reference_code' => ['nullable', 'string', 'max:100'],
            'payment.note' => ['nullable', 'string', 'max:255'],
            'payment.idempotency_key' => ['nullable', 'string', 'max:80'],
        ]);

        // A shipping order without any address text is almost certainly a
        // data-entry mistake — ask once instead of shipping to nothing.
        if (($validated['fulfillment_method'] ?? '') === 'shipping'
            && trim((string) ($validated['shipping_address']['address_line'] ?? '')) === '') {
            return back()->withErrors(['shipping_address.address_line' => 'برای ارسال با پست، نشانی گیرنده الزامی است.']);
        }

        if (($validated['status'] ?? 'confirmed') === 'draft' && ($validated['payment'] ?? null) !== null) {
            return back()->withErrors(['payment' => 'سفارش پیش‌نویس هنوز پرداخت ندارد؛ ابتدا آن را تأیید کنید.']);
        }

        try {
            $order = $this->orders->create($this->context->tenant(), $request->user(), [
                'customer_id' => $validated['customer_id'],
                'items' => array_map(fn (array $item): array => [
                    'variant_id' => $item['variant_id'],
                    'quantity' => (int) $item['quantity'],
                    'discount_amount' => $item['discount_amount'] ?? '0',
                ], $validated['items']),
                'sales_channel' => $validated['sales_channel'],
                'fulfillment_method' => $validated['fulfillment_method'],
                'status' => $validated['status'] ?? 'confirmed',
                'discount_amount' => $validated['discount_amount'] ?? '0',
                'shipping_amount' => $validated['shipping_amount'] ?? '0',
                'customer_note' => $validated['customer_note'] ?? null,
                'internal_note' => $validated['internal_note'] ?? null,
                'shipping_address' => $validated['shipping_address'] ?? null,
                'payment' => ($validated['payment'] ?? null) !== null ? [
                    'amount' => $validated['payment']['amount'],
                    'method' => $validated['payment']['method'],
                    'reference_code' => $validated['payment']['reference_code'] ?? null,
                    'note' => $validated['payment']['note'] ?? null,
                    'idempotency_key' => $validated['payment']['idempotency_key'] ?? null,
                ] : null,
                'idempotency_key' => $validated['idempotency_key'],
            ]);
        } catch (SalesException $exception) {
            return back()->withErrors(['order' => $exception->getMessage()]);
        }

        $message = $order->wasRecentlyCreated
            ? 'سفارش '.$order->order_number.' ثبت شد.'
            : 'ارسال تکراری نادیده گرفته شد؛ سفارش '.$order->order_number.' پیش‌تر ثبت شده بود.';

        return redirect()
            ->route('orders.show', $order)
            ->with('success', $message);
    }

    /** Order detail: items, payments, stock movements, actions. */
    public function show(Request $request, Order $order): Response
    {
        $order->load([
            'customer:id,tenant_id,customer_number,display_name,normalized_name',
            'items',
            'payments',
            'creator:id,name',
        ]);

        $movements = InventoryMovement::query()
            ->where('reference_type', 'order')
            ->where('reference_id', $order->id)
            ->with('variant:id,tenant_id,variant_name,sku')
            ->orderBy('occurred_at')
            ->get();

        return Inertia::render('orders/Show', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'sales_channel' => $order->sales_channel->value,
                'fulfillment_method' => $order->fulfillment_method->value,
                'customer' => [
                    'id' => $order->customer->id,
                    'display_name' => $order->customer->display_name,
                    'customer_number' => $order->customer->customer_number,
                ],
                'placed_at' => $order->placed_at?->toISOString(),
                'completed_at' => $order->completed_at?->toISOString(),
                'returned_at' => $order->returned_at?->toISOString(),
                'return_note' => $order->return_note,
                'subtotal_amount' => $order->subtotal_amount,
                'discount_amount' => $order->discount_amount,
                'shipping_amount' => $order->shipping_amount,
                'total_amount' => $order->total_amount,
                'currency_code' => $order->currency_code,
                'payment_status' => $this->orders->paymentStatus($order)->value,
                'paid_amount' => $this->paidAmount($order),
                'customer_note' => $order->customer_note,
                'internal_note' => $order->internal_note,
                'shipping_address' => $order->shipping_address_snapshot,
                'creator_name' => $order->creator?->name,
                'items' => $order->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'product_name' => $item->product_name_snapshot,
                    'variant_name' => $item->variant_name_snapshot,
                    'attributes' => $item->variant_attributes_snapshot,
                    'sku' => $item->sku_snapshot,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_amount' => $item->discount_amount,
                    'line_total' => $item->line_total,
                ])->all(),
                'payments' => $order->payments->map(fn ($payment): array => [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'currency_code' => $payment->currency_code,
                    'method' => $payment->payment_method->value,
                    'status' => $payment->status->value,
                    'paid_at' => $payment->paid_at?->toISOString(),
                    'reference_code' => $payment->reference_code,
                    'note' => $payment->note,
                    'creator_name' => $payment->creator?->name,
                ])->all(),
                'allowed_transitions' => array_map(
                    fn (OrderStatus $status): string => $status->value,
                    $order->status->allowedTransitions(),
                ),
            ],
            'movements' => $movements->map(fn (InventoryMovement $movement): array => [
                'id' => $movement->id,
                'variant_label' => $movement->variant?->variant_name ?: $movement->variant?->sku,
                'movement_type' => $movement->movement_type->value,
                'quantity_delta' => $movement->quantity_delta,
                'occurred_at' => $movement->occurred_at->toISOString(),
            ])->all(),
            'canManage' => $request->user()->can('orders.manage'),
            'canManagePayments' => $request->user()->can('payments.manage'),
        ]);
    }

    /** Generic status transition (processing/shipped/delivered/completed). */
    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(array_column(OrderStatus::cases(), 'value'))],
        ]);

        $target = OrderStatus::from($validated['status']);

        // Cancel/return have dedicated endpoints with side effects.
        if (in_array($target, [OrderStatus::Cancelled, OrderStatus::Returned], true)) {
            return back()->withErrors(['status' => 'برای لغو یا مرجوعی از دکمه مخصوص همان عملیات استفاده کنید.']);
        }

        try {
            $this->orders->transition($order, $request->user(), $target);
        } catch (SalesException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return back()->with('success', 'وضعیت سفارش به «'.$target->value.'» تغییر کرد.');
    }

    /** Cancel before delivery — restores stock via reverse movements. */
    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->orders->cancel($order, $request->user(), $validated['reason'] ?? null);
        } catch (SalesException $exception) {
            return back()->withErrors(['order' => $exception->getMessage()]);
        }

        return back()->with('success', 'سفارش لغو شد و موجودی برگشت.');
    }

    /** Full-order return after delivery — return_in movements. */
    public function markReturned(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->orders->markReturned($order, $request->user(), $validated['note'] ?? null);
        } catch (SalesException $exception) {
            return back()->withErrors(['order' => $exception->getMessage()]);
        }

        return back()->with('success', 'سفارش مرجوع ثبت شد و موجودی برگشت.');
    }

    // --- internals --------------------------------------------------------

    /**
     * Persian/Arabic digits and thousand separators from the mobile form
     * are folded to plain ASCII integer strings BEFORE validation, so the
     * rules stay strict and Money math stays integer (spec 5.5).
     */
    private function normalizeNumericInputs(Request $request): void
    {
        $request->merge([
            'discount_amount' => Money::normalize($request->input('discount_amount')),
            'shipping_amount' => Money::normalize($request->input('shipping_amount')),
        ]);

        $items = $request->input('items');

        if (is_array($items)) {
            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $items[$index]['quantity'] = PersianText::digitsOnly((string) ($item['quantity'] ?? ''));
                $items[$index]['discount_amount'] = Money::normalize($item['discount_amount'] ?? null);
            }

            $request->merge(['items' => $items]);
        }

        $payment = $request->input('payment');

        if (is_array($payment)) {
            $payment['amount'] = Money::normalize($payment['amount'] ?? null);
            $request->merge(['payment' => $payment]);
        }
    }

    /** @return array<string, mixed> */
    private function listRow(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'sales_channel' => $order->sales_channel->value,
            'customer_name' => $order->customer?->display_name,
            'customer_number' => $order->customer?->customer_number,
            'items_count' => $order->items->count(),
            'total_amount' => $order->total_amount,
            'currency_code' => $order->currency_code,
            'payment_status' => $this->orders->paymentStatus($order)->value,
            'placed_at' => $order->placed_at?->toISOString(),
        ];
    }

    private function paidAmount(Order $order): string
    {
        $order->loadMissing('payments');

        $paid = '0';

        foreach ($order->payments as $payment) {
            if ($payment->status->value === 'recorded') {
                $paid = Money::add($paid, $payment->amount);
            }
        }

        return $paid;
    }

    /** @return array<string, mixed> */
    private function productOption(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'category_id' => $product->category_id,
            'track_inventory' => $product->track_inventory,
            'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'label' => $variant->variant_name ?: ($variant->sku ?: $product->name),
                'sku' => $variant->sku,
                'attributes' => $variant->attributes,
                'sale_price' => $variant->sale_price,
                'currency_code' => $variant->currency_code,
                'stock_on_hand' => $variant->stock_on_hand,
            ])->all(),
        ];
    }
}
