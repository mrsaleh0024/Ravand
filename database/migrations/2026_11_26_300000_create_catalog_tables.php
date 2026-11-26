<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product catalog tables (phase 03, spec section 5.3).
 *
 * Design rules carried over from phase 02 (ADR-024):
 * - ULID string keys everywhere (ADR-004).
 * - tenant_id on every row + composite FKs, so cross-tenant parent links
 *   are impossible at the database level (spec 2.2).
 * - Redundant unique (tenant_id, id) indexes exist only to serve as the
 *   parent key of composite foreign keys from child tables.
 * - Amounts are DECIMAL(20,0) integers — no floats (spec 5.5).
 * - stock_on_hand is a CACHE; inventory_movements stays the source of
 *   truth and both change inside one DB transaction (spec 5.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('tenant_id', 26);
            $table->string('parent_id', 26)->nullable();   // self-referencing tree (chador → black chador, …)
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'parent_id']);
            $table->unique(['tenant_id', 'id']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('product_categories')->nullOnDelete();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('tenant_id', 26);
            $table->string('category_id', 26)->nullable();  // uncatalogued products allowed
            $table->string('name', 160);
            $table->string('normalized_name', 200);          // Persian/Latin tolerant search target
            $table->text('description')->nullable();
            $table->string('brand', 120)->nullable();
            $table->string('product_type', 20)->default('physical'); // physical in MVP, extensible
            $table->boolean('is_active')->default(true);
            $table->boolean('track_inventory')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'created_at']);
            $table->index(['tenant_id', 'normalized_name']);
            $table->unique(['tenant_id', 'id']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'category_id'])
                ->references(['tenant_id', 'id'])->on('product_categories')
                ->nullOnDelete();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('tenant_id', 26);
            $table->string('product_id', 26);
            $table->string('sku', 100)->nullable();          // unique per tenant WHEN present
            $table->string('variant_name', 160)->nullable();
            $table->json('attributes')->nullable();          // schema-controlled {color, size, fabric, …}
            $table->decimal('sale_price', 20, 0);
            $table->decimal('cost_price', 20, 0)->nullable(); // profit reports later, restricted access
            $table->string('currency_code', 3)->default('IRR');
            $table->boolean('is_active')->default(true);
            $table->bigInteger('stock_on_hand')->default(0); // cache — movements are the truth
            $table->timestamps();

            $table->unique(['tenant_id', 'sku']);            // multiple NULL skus allowed on both drivers
            $table->index(['tenant_id', 'is_active']);
            $table->index(['product_id']);
            $table->unique(['tenant_id', 'id']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'product_id'])
                ->references(['tenant_id', 'id'])->on('products')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
