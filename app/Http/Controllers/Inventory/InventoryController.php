<?php

namespace App\Http\Controllers\Inventory;

use App\Domain\Audit\AuditLogger;
use App\Domain\Sales\InventoryService;
use App\Domain\Sales\MovementType;
use App\Domain\Sales\SalesException;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Support\PersianText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stock overview + manual movement recording (phase 03, spec 5.4).
 *
 * The movement ledger is the source of truth; the stock list shows the
 * cached stock_on_hand which only ever changes inside a movement
 * transaction. Order-driven movements (sale_out / cancellation_restore /
 * return_in) are NOT editable here — only visible in the history.
 */
class InventoryController extends Controller
{
    private const PAGE_SIZE = 20;

    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
        private readonly InventoryService $inventory,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $variantId = (string) $request->query('variant', '');

        $stockQuery = ProductVariant::query()
            ->with('product')
            ->where('is_active', true);

        if ($search !== '') {
            $normalized = PersianText::normalize($search);
            $digits = PersianText::digitsOnly($search);

            $stockQuery->where(function ($q) use ($normalized, $digits): void {
                $q->whereHas('product', fn ($p) => $p->where('normalized_name', 'like', "%{$normalized}%"))
                    ->orWhere('variant_name', 'like', "%{$normalized}%");

                if ($digits !== '') {
                    $q->orWhere('sku', 'like', "%{$digits}%");
                }
            });
        }

        $stock = $stockQuery
            ->orderBy('stock_on_hand')
            ->paginate(self::PAGE_SIZE, ['*'], 'stock_page')
            ->withQueryString();

        $movementQuery = InventoryMovement::query()
            ->with(['variant.product', 'creator:id,name']);

        if ($variantId !== '') {
            $movementQuery->where('product_variant_id', $variantId);
        }

        $movements = $movementQuery
            ->orderByDesc('occurred_at')
            ->paginate(self::PAGE_SIZE, ['*'], 'movement_page')
            ->withQueryString();

        return Inertia::render('inventory/Index', [
            'stock' => $stock->through(fn (ProductVariant $variant): array => $this->stockRow($variant)),
            'movements' => $movements->through(fn (InventoryMovement $movement): array => $this->movementRow($movement)),
            'variantOptions' => $this->variantOptions(),
            'filters' => [
                'search' => $search,
                'variant' => $variantId,
            ],
        ]);
    }

    /** Record a manual movement (opening / purchase / adjustment). */
    public function store(Request $request): RedirectResponse
    {
        // Persian/Arabic digits from the mobile form → ASCII before validation.
        $request->merge([
            'quantity' => PersianText::digitsOnly((string) $request->input('quantity')),
        ]);

        $validated = $request->validate([
            'product_variant_id' => ['required', 'string', 'max:26'],
            'movement_type' => ['required', 'string', Rule::in(array_column(MovementType::manualChoices(), 'value'))],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'reason' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:80'],
        ]);

        /** @var ProductVariant $variant */
        $variant = ProductVariant::query()->findOrFail($validated['product_variant_id']);

        $type = MovementType::from($validated['movement_type']);
        $delta = $type->isInbound() ? $validated['quantity'] : -1 * $validated['quantity'];

        if (in_array($type, [MovementType::AdjustmentIn, MovementType::AdjustmentOut], true)
            && ($validated['reason'] ?? '') === '') {
            return back()->withErrors(['reason' => 'برای اصلاح موجودی، ثبت دلیل الزامی است.']);
        }

        try {
            $movement = $this->inventory->record(
                variant: $variant,
                type: $type,
                quantityDelta: $delta,
                idempotencyKey: 'manual:'.($validated['idempotency_key'] ?? (string) Str::ulid()),
                actor: $request->user(),
                context: [
                    'reference_type' => 'manual',
                    'reason' => $validated['reason'] ?? null,
                ],
            );
        } catch (SalesException $exception) {
            return back()->withErrors(['quantity' => $exception->getMessage()]);
        }

        $this->audit->log(
            action: 'inventory.movement_recorded',
            tenant: $this->context->tenant(),
            actor: $request->user(),
            entityType: 'inventory_movement',
            entityId: $movement->id,
            metadata: [
                'movement_type' => $movement->movement_type->value,
                'quantity_delta' => $movement->quantity_delta,
                'product_variant_id' => $movement->product_variant_id,
                'reason' => $movement->reason,
            ],
        );

        return back()->with('success', 'حرکت موجودی ثبت شد.');
    }

    // --- internals --------------------------------------------------------

    /** @return array<string, mixed> */
    private function stockRow(ProductVariant $variant): array
    {
        return [
            'id' => $variant->id,
            'label' => $variant->variant_name ?: ($variant->sku ?: $variant->product?->name),
            'product_name' => $variant->product?->name,
            'sku' => $variant->sku,
            'stock_on_hand' => $variant->stock_on_hand,
            'tracks' => $variant->product?->track_inventory ?? true,
            'attributes' => $variant->attributes,
        ];
    }

    /** @return array<string, mixed> */
    private function movementRow(InventoryMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'variant_label' => $movement->variant?->variant_name ?: ($movement->variant?->sku ?: $movement->variant?->product?->name),
            'product_name' => $movement->variant?->product?->name,
            'movement_type' => $movement->movement_type->value,
            'quantity_delta' => $movement->quantity_delta,
            'reason' => $movement->reason,
            'reference_type' => $movement->reference_type,
            'actor_name' => $movement->creator?->name,
            'occurred_at' => $movement->occurred_at->toISOString(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function variantOptions(): array
    {
        return ProductVariant::query()
            ->with('product:id,name')
            ->where('is_active', true)
            ->orderBy('created_at')
            ->get()
            ->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'label' => $variant->variant_name ?: ($variant->sku ?: $variant->product?->name),
                'product_name' => $variant->product?->name,
                'stock_on_hand' => $variant->stock_on_hand,
            ])
            ->all();
    }
}
