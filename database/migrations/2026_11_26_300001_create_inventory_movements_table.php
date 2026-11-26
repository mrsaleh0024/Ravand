<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory movements ledger (phase 03, spec section 5.4).
 *
 * Append-only: stock is derived from the signed quantity_delta rows and a
 * cached stock_on_hand on the variant; both change inside one transaction.
 * The idempotency_key (nullable, unique per tenant) makes retries and
 * double-submits collapse into a single movement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('tenant_id', 26);
            $table->string('product_variant_id', 26);
            $table->string('idempotency_key', 120)->nullable();
            $table->string('movement_type', 30); // opening|purchase_in|sale_out|return_in|adjustment_in|adjustment_out|cancellation_restore
            $table->bigInteger('quantity_delta'); // signed
            $table->string('reference_type', 30)->nullable(); // order|payment|manual
            $table->string('reference_id', 26)->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestamp('occurred_at');
            $table->string('created_by_user_id', 26)->nullable();
            $table->timestamp('created_at')->nullable(); // append-only: no updated_at

            $table->unique(['tenant_id', 'idempotency_key']); // NULLs never collide
            $table->index(['tenant_id', 'product_variant_id', 'occurred_at']);
            $table->index(['reference_type', 'reference_id']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'product_variant_id'])
                ->references(['tenant_id', 'id'])->on('product_variants')
                ->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
