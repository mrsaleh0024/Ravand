<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer CRM core tables (phase 02, spec section 5.2).
 *
 * Design rules applied:
 * - ULID string keys everywhere (ADR-004).
 * - tenant_id on every row + composite FKs where supported, so joining a
 *   row to another tenant's parent is hard at the database level (spec 2.2).
 * - NO unique constraint on contact values: phones may be shared inside a
 *   family; duplicates are surfaced as a warning, not a validation error.
 * - Normalized search columns (`normalized_*`) — the original display text
 *   is never overwritten (spec 2.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('tenant_id', 26);
            $table->string('customer_number', 20);
            $table->string('display_name', 120);
            $table->string('normalized_name', 160);
            $table->string('first_name', 80)->nullable();
            $table->string('last_name', 80)->nullable();
            $table->string('gender', 20)->nullable();          // female | male | other | not_specified
            $table->date('birth_date')->nullable();            // Gregorian DATE (spec 4.3)
            $table->string('customer_status', 20)->default('active'); // active | inactive | blocked | archived
            $table->string('acquisition_channel', 30)->default('unknown');
            $table->string('acquisition_note', 255)->nullable();
            $table->string('preferred_contact_channel', 30)->nullable();
            $table->string('preferred_language', 10)->default('fa');
            $table->string('created_by_user_id', 26)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'customer_number']);
            $table->index(['tenant_id', 'customer_status', 'created_at']);
            $table->index(['tenant_id', 'normalized_name']);
            // Parent key for composite (tenant_id, id) foreign keys from the
            // child tables — redundant uniqueness, required by the FK.
            $table->unique(['tenant_id', 'id']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('tenant_id', 26);
            $table->string('customer_id', 26);
            $table->string('contact_type', 20)->default('mobile'); // mobile | landline | email | other
            $table->string('label', 60)->nullable();
            $table->string('display_value', 120);
            $table->string('normalized_value', 120);            // search target — NOT unique
            $table->string('country_code', 8)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'normalized_value']);
            $table->index(['customer_id', 'contact_type']);

            $table->foreign(['tenant_id', 'customer_id'])
                ->references(['tenant_id', 'id'])->on('customers')
                ->cascadeOnDelete();
        });

        Schema::create('customer_social_accounts', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('tenant_id', 26);
            $table->string('customer_id', 26);
            $table->string('platform', 20);                     // instagram | telegram | whatsapp | other
            $table->string('username', 120);
            $table->string('normalized_handle', 120);
            $table->string('profile_url', 255)->nullable();
            $table->string('display_name', 120)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'normalized_handle']);
            $table->index('customer_id');

            $table->foreign(['tenant_id', 'customer_id'])
                ->references(['tenant_id', 'id'])->on('customers')
                ->cascadeOnDelete();
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('tenant_id', 26);
            $table->string('customer_id', 26);
            $table->string('title', 60);
            $table->string('recipient_name', 120)->nullable();
            $table->string('recipient_phone', 20)->nullable();
            $table->string('province', 60)->nullable();
            $table->string('city', 60)->nullable();
            $table->string('district', 60)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('address_line', 500)->nullable();
            $table->string('delivery_notes', 500)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'city']);
            $table->index('customer_id');

            $table->foreign(['tenant_id', 'customer_id'])
                ->references(['tenant_id', 'id'])->on('customers')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customer_social_accounts');
        Schema::dropIfExists('customer_contacts');
        Schema::dropIfExists('customers');
    }
};
