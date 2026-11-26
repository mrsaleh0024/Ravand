<?php

namespace Tests\Feature\Sales;

use App\Domain\Sales\InventoryService;
use App\Domain\Sales\MovementType;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenancy;
use Tests\Concerns\SeedsCatalog;
use Tests\TestCase;

/**
 * Phase-03 inventory smoke checks (spec section 5.4 / 10):
 * opening stock, append-only ledger, negative-stock guard with a
 * user-understandable Persian error, idempotent movements, role limits.
 */
class InventoryTest extends TestCase
{
    use CreatesTenancy, RefreshDatabase, SeedsCatalog;

    public function test_opening_movement_sets_the_cached_stock(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه انبار');
        $seed = $this->seedProduct($tenant);
        $this->addStock($owner, $seed['variantA'], 12);

        $this->assertSame(12, $seed['variantA']->refresh()->stock_on_hand);

        $movement = InventoryMovement::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertSame(MovementType::Opening, $movement->movement_type);
        $this->assertSame(12, $movement->quantity_delta);
    }

    public function test_manual_movement_can_be_recorded_through_the_panel(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه حرکت');
        $seed = $this->seedProduct($tenant);

        $response = $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('inventory.movements.store'), [
                'product_variant_id' => $seed['variantA']->id,
                'movement_type' => 'purchase_in',
                'quantity' => '۵', // Persian digits from the form
                'reason' => 'خرید از بازار تهران',
                'idempotency_key' => 'test-manual-1',
            ]);

        $response->assertRedirect()->assertSessionHas('success');
        $this->assertSame(5, $seed['variantA']->refresh()->stock_on_hand);
    }

    public function test_negative_stock_is_rejected_with_an_understandable_error(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه منفی');
        $seed = $this->seedProduct($tenant);
        $this->addStock($owner, $seed['variantA'], 3);

        $response = $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('inventory.movements.store'), [
                'product_variant_id' => $seed['variantA']->id,
                'movement_type' => 'adjustment_out',
                'quantity' => 10,
                'reason' => 'گم شده',
            ]);

        $response->assertSessionHasErrors('quantity');
        $this->assertStringContainsString('کافی نیست', session('errors')->default->first('quantity'));
        $this->assertSame(3, $seed['variantA']->refresh()->stock_on_hand);
    }

    public function test_adjustments_require_a_reason(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه دلیل');
        $seed = $this->seedProduct($tenant);

        $this->actingAs($owner)
            ->withCurrentTenant($owner, $tenant)
            ->post(route('inventory.movements.store'), [
                'product_variant_id' => $seed['variantA']->id,
                'movement_type' => 'adjustment_in',
                'quantity' => 2,
                'reason' => '',
            ])
            ->assertSessionHasErrors('reason');
    }

    public function test_replaying_the_same_idempotency_key_records_one_movement(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه تکرار');
        $seed = $this->seedProduct($tenant);

        $this->addStock($owner, $seed['variantA'], 7);

        // Direct service replay with the SAME key (HTTP retry simulation).
        app(InventoryService::class)->record(
            variant: $seed['variantA'],
            type: MovementType::Opening,
            quantityDelta: 7,
            idempotencyKey: 'seed:opening:'.$seed['variantA']->id,
            actor: $owner,
        );

        $this->assertSame(1, InventoryMovement::query()
            ->where('tenant_id', $tenant->id)
            ->where('product_variant_id', $seed['variantA']->id)
            ->count());
        $this->assertSame(7, $seed['variantA']->refresh()->stock_on_hand, 'stock must not be double-counted');
    }

    public function test_the_ledger_is_append_only(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه دفتر');
        $seed = $this->seedProduct($tenant);
        $this->addStock($owner, $seed['variantA'], 4);

        $movement = InventoryMovement::query()->where('tenant_id', $tenant->id)->first();

        $movement->update(['quantity_delta' => 999]);
        $movement->delete();

        $this->assertDatabaseHas('inventory_movements', [
            'id' => $movement->id,
            'quantity_delta' => 4,
        ]);
    }

    public function test_operator_cannot_record_movements(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه دسترسی');
        $seed = $this->seedProduct($tenant);

        $operator = User::factory()->create();
        $this->attachMembership($operator, $tenant, 'operator');

        $this->actingAs($operator)
            ->withCurrentTenant($operator, $tenant)
            ->post(route('inventory.movements.store'), [
                'product_variant_id' => $seed['variantA']->id,
                'movement_type' => 'opening',
                'quantity' => 5,
            ])
            ->assertForbidden();

        // But the stock overview is readable for everyone who records sales.
        $this->actingAs($operator)
            ->withCurrentTenant($operator, $tenant)
            ->get(route('inventory.index'))
            ->assertOk();
    }

    public function test_movements_are_tenant_isolated(): void
    {
        [$owner, $tenant] = $this->createOwner('فروشگاه الف');
        [$ownerB, $tenantB] = $this->createOwner('فروشگاه ب');
        $seed = $this->seedProduct($tenant);
        $this->addStock($owner, $seed['variantA'], 9);

        $this->assertSame(
            0,
            InventoryMovement::query()->where('tenant_id', $tenantB->id)->count(),
        );

        // A tenant-B actor cannot act on tenant-A's variant: the manual
        // movement endpoint resolves the variant inside its own tenant only.
        $this->actingAs($ownerB)
            ->withCurrentTenant($ownerB, $tenantB)
            ->post(route('inventory.movements.store'), [
                'product_variant_id' => $seed['variantA']->id,
                'movement_type' => 'opening',
                'quantity' => 1,
            ])
            ->assertNotFound();

        $this->assertSame(9, $seed['variantA']->refresh()->stock_on_hand);
    }
}
