<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only audit trail (spec section 8.6). Login events are platform
     * level (tenant_id null); domain events carry the tenant context.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('tenant_id', 26)->nullable()->index();
            $table->string('actor_user_id', 26)->nullable()->index();
            $table->string('action')->index();
            $table->string('entity_type')->nullable();
            $table->string('entity_id', 26)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'action', 'occurred_at']);
            $table->index(['entity_type', 'entity_id']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
