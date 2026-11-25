<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tenant = one store. Shared database / shared schema with tenant_id
     * scoping on every domain table (ADR-003).
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active')->index();
            // IRT is the common retail unit in Iran; code stays stable per record
            // and future financial rows snapshot their own currency_code (ADR-006).
            $table->string('default_currency', 3)->default('IRT');
            $table->string('timezone')->default('Asia/Tehran');
            $table->string('locale')->default('fa-IR');
            $table->string('calendar')->default('jalali');
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
