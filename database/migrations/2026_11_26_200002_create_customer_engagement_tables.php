<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer engagement tables (phase 02, spec section 5.2):
 * tags, customer_tag, notes and interactions.
 *
 * - Tags are a tenant-level shared vocabulary (unique slug per tenant);
 *   they never replace status or transactional data.
 * - Notes are plain text only (no raw HTML accepted anywhere).
 * - Interactions store a seller-typed SUMMARY, never archived social
 *   message content (spec 5.2 / ADR scope rule).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('tenant_id', 26);
            $table->string('name', 80);
            $table->string('slug', 100);               // stable search key, unique per tenant
            $table->string('color_token', 40)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            // Parent key for the composite (tenant_id, id) FK from customer_tag.
            $table->unique(['tenant_id', 'id']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('customer_tag', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('tenant_id', 26);
            $table->string('customer_id', 26);
            $table->string('tag_id', 26);
            $table->string('created_by_user_id', 26)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['customer_id', 'tag_id']);
            $table->index(['tenant_id', 'tag_id']);

            $table->foreign(['tenant_id', 'customer_id'])
                ->references(['tenant_id', 'id'])->on('customers')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'tag_id'])
                ->references(['tenant_id', 'id'])->on('tags')
                ->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('customer_notes', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('tenant_id', 26);
            $table->string('customer_id', 26);
            $table->text('body');                      // plain text; HTML never rendered
            $table->string('visibility', 20)->default('private'); // private | team
            $table->string('created_by_user_id', 26)->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'visibility']);

            $table->foreign(['tenant_id', 'customer_id'])
                ->references(['tenant_id', 'id'])->on('customers')
                ->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('customer_interactions', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('tenant_id', 26);
            $table->string('customer_id', 26);
            $table->string('interaction_type', 30);    // message | call | follow_up | complaint | question | purchase_related | other
            $table->string('channel', 20);             // instagram | whatsapp | telegram | phone | in_person | other
            $table->string('subject', 255)->nullable();
            $table->text('summary');                   // seller-typed summary, plain text
            $table->string('outcome', 30)->nullable(); // purchase | no_purchase | waiting | resolved | unresolved | other
            $table->timestamp('occurred_at');
            $table->timestamp('follow_up_at')->nullable();
            $table->string('follow_up_status', 20)->nullable(); // pending | done | cancelled
            $table->string('related_order_id', 26)->nullable(); // orders land in phase 03
            $table->string('created_by_user_id', 26)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['tenant_id', 'follow_up_status', 'follow_up_at']);
            $table->index('customer_id');

            $table->foreign(['tenant_id', 'customer_id'])
                ->references(['tenant_id', 'id'])->on('customers')
                ->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_interactions');
        Schema::dropIfExists('customer_notes');
        Schema::dropIfExists('customer_tag');
        Schema::dropIfExists('tags');
    }
};
