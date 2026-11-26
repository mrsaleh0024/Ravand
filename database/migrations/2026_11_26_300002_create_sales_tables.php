<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sales tables: orders, order_items, payments (phase 03, spec section 5.5).
 *
 * Financial rules baked into the schema:
 * - DECIMAL(20,0) integer amounts — never floats.
 * - Every amount-bearing row snapshots its own currency_code; changing the
 *   tenant currency only affects future rows (ADR-006).
 * - order_items snapshot name/attributes/sku/price at purchase time, so
 *   later catalog edits never rewrite sales history.
 * - idempotency_key unique per tenant on orders and payments collapses
 *   double-submits and retries into one record.
 * - No DB-level uniqueness on items/payments beyond the tenant idempotency
 *   keys; business rules live in OrderService and are transactional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('tenant_id', 26);
            $table->string('customer_id', 26);
            $table->string('idempotency_key', 120)->nullable();
            $table->string('order_number', 30);              // ORD-000001, unique per tenant
            $table->string('sales_channel', 20);             // instagram|whatsapp|telegram|direct|in_person|other
            $table->string('fulfillment_method', 20);        // shipping|pickup|in_person
            $table->string('status', 20)->default('confirmed'); // draft|confirmed|processing|shipped|delivered|completed|cancelled|returned
            $table->timestamp('returned_at')->nullable();
            $table->string('return_note', 1000)->nullable();
            $table->timestamp('placed_at');
            $table->timestamp('completed_at')->nullable();
            $table->decimal('subtotal_amount', 20, 0);
            $table->decimal('discount_amount', 20, 0)->default(0);
            $table->decimal('shipping_amount', 20, 0)->default(0);
            $table->decimal('total_amount', 20, 0);
            $table->string('currency_code', 3);              // snapshot of tenant currency at placement
            $table->text('customer_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->json('shipping_address_snapshot')->nullable(); // address as entered at sale time
            $table->string('created_by_user_id', 26)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'order_number']);
            $table->unique(['tenant_id', 'idempotency_key']); // NULLs never collide
            $table->index(['tenant_id', 'status', 'placed_at']);
            $table->index(['tenant_id', 'customer_id', 'placed_at']);
            $table->unique(['tenant_id', 'id']);              // parent key for order_items/payments FKs

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'customer_id'])
                ->references(['tenant_id', 'id'])->on('customers')
                ->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('tenant_id', 26);
            $table->string('order_id', 26);
            $table->string('product_id', 26)->nullable();    // survives product archival/deletion
            $table->string('product_variant_id', 26)->nullable();
            $table->string('product_name_snapshot', 160);
            $table->string('variant_name_snapshot', 160)->nullable();
            $table->json('variant_attributes_snapshot')->nullable();
            $table->string('sku_snapshot', 100)->nullable();
            $table->unsignedInteger('quantity');             // positive int
            $table->decimal('unit_price', 20, 0);            // variant price at purchase
            $table->decimal('discount_amount', 20, 0)->default(0);
            $table->decimal('line_total', 20, 0);
            $table->decimal('cost_price_snapshot', 20, 0)->nullable();
            $table->timestamps();

            $table->index(['order_id']);
            $table->index(['tenant_id', 'product_id']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'order_id'])
                ->references(['tenant_id', 'id'])->on('orders')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'product_id'])
                ->references(['tenant_id', 'id'])->on('products')
                ->nullOnDelete();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('tenant_id', 26);
            $table->string('order_id', 26);
            $table->string('idempotency_key', 120)->nullable();
            $table->decimal('amount', 20, 0);
            $table->string('currency_code', 3);              // matches the order currency
            $table->string('payment_method', 20);            // cash|card_to_card|pos|online|bank_transfer|other
            $table->string('status', 20)->default('recorded'); // recorded|refunded|voided
            $table->timestamp('paid_at')->nullable();
            $table->string('reference_code', 100)->nullable();
            $table->string('note', 255)->nullable();
            $table->string('created_by_user_id', 26)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['order_id']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'order_id'])
                ->references(['tenant_id', 'id'])->on('orders')
                ->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
