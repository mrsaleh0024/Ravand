<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loyalty club tables (phase 04, spec section 10 / phase 04).
 *
 * Rules baked into the schema:
 * - `loyalty_settings` is a per-tenant singleton (unique tenant_id) holding
 *   the enable flag and the single earn rule: every `amount_step` of spend
 *   (integer minor units) earns `points_per_step` points.
 * - `loyalty_transactions` is an APPEND-ONLY ledger; rows are never updated
 *   or deleted (enforced again at the model layer). Corrections are new
 *   opposite rows (`reverse` / `adjust`).
 * - The balance is always derived (Σ signed `points`); no cached balance
 *   column exists — spec phase 04: derived from source data.
 * - Idempotency: system rows carry `order:{id}:earn` / `order:{id}:reverse`
 *   keys, unique per tenant, so completing/returning an order twice can
 *   never double-award or double-reverse.
 * - `reversal_of` links a reversal back to the original earn row.
 * - `amount_basis` snapshots the order total an earn was computed from, so
 *   later rule changes never rewrite what was actually granted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_settings', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('tenant_id', 26);
            $table->boolean('points_enabled')->default(false);
            $table->decimal('amount_step', 20, 0)->default(1000000); // spend step, minor units
            $table->unsignedInteger('points_per_step')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id']); // one settings row per tenant

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('tenant_id', 26);
            $table->string('customer_id', 26);
            $table->string('order_id', 26)->nullable();          // set for earn/reverse
            $table->string('idempotency_key', 120)->nullable();  // system keys; NULL for manual rows
            $table->string('transaction_type', 20);              // earn|reverse|adjust
            $table->integer('points');                           // signed delta (+earn / −reverse / ±adjust)
            $table->decimal('amount_basis', 20, 0)->nullable();  // order total the earn was computed from
            $table->string('reason', 1000)->nullable();          // mandatory for adjust, provided for reverse
            $table->string('reversal_of', 26)->nullable();       // self-link to the reversed earn row
            $table->string('created_by_user_id', 26)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key']);    // NULLs never collide
            $table->unique(['tenant_id', 'id']);                 // parent key for the self FK
            $table->index(['tenant_id', 'customer_id', 'created_at']);
            $table->index(['order_id']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'customer_id'])
                ->references(['tenant_id', 'id'])->on('customers')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'order_id'])
                ->references(['tenant_id', 'id'])->on('orders')
                ->cascadeOnDelete();
            $table->foreign('reversal_of')
                ->references('id')->on('loyalty_transactions')
                ->restrictOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_settings');
    }
};
