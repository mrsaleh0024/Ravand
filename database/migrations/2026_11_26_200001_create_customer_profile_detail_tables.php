<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer profile detail tables (phase 02, spec section 5.2):
 * consents, preferences and measurements.
 *
 * - Marketing consent defaults to `unknown` — recording a phone number is
 *   NEVER interpreted as marketing permission (spec 8.5).
 * - Preferences are structured rows (category + stable value_code + Persian
 *   label snapshot), not a free-form blob.
 * - Measurements are historical rows (append-only mindset): sizes change
 *   over time and old values must stay queryable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_consents', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('tenant_id', 26);
            $table->string('customer_id', 26);
            $table->string('channel', 20);            // sms | whatsapp | instagram | telegram | email | phone
            $table->string('purpose', 20);            // transactional | marketing | service
            $table->string('status', 20)->default('unknown'); // granted | denied | withdrawn | unknown
            $table->timestamp('captured_at');
            $table->string('capture_method', 20)->default('manual'); // manual | form | message | other
            $table->string('evidence_note', 255)->nullable();
            $table->string('captured_by_user_id', 26)->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'channel', 'purpose']);
            $table->index(['tenant_id', 'status']);

            $table->foreign(['tenant_id', 'customer_id'])
                ->references(['tenant_id', 'id'])->on('customers')
                ->cascadeOnDelete();
            $table->foreign('captured_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('customer_preferences', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('tenant_id', 26);
            $table->string('customer_id', 26);
            $table->string('preference_category', 40); // product_category | color | fabric | style | occasion | price_sensitivity | budget_level | brand | other
            $table->string('value_code', 80);          // stable English code, e.g. black
            $table->string('value_label', 120);        // Persian label snapshot
            $table->string('preference_level', 20)->nullable(); // like | dislike | neutral
            $table->string('source', 30)->default('customer_stated'); // customer_stated | purchase_inferred | staff_observed
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'preference_category']);
            $table->index(['tenant_id', 'value_code']);

            $table->foreign(['tenant_id', 'customer_id'])
                ->references(['tenant_id', 'id'])->on('customers')
                ->cascadeOnDelete();
        });

        Schema::create('customer_measurements', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('tenant_id', 26);
            $table->string('customer_id', 26);
            $table->string('measurement_code', 60);    // manteau_size | trouser_size | ...
            $table->string('value_text', 120)->nullable();
            $table->decimal('value_number', 10, 2)->nullable();
            $table->string('unit', 20)->nullable();    // cm | size | qovare | ...
            $table->timestamp('recorded_at');
            $table->string('source', 30)->default('staff_measured'); // customer_stated | staff_measured | purchase_inferred
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'measurement_code']);

            $table->foreign(['tenant_id', 'customer_id'])
                ->references(['tenant_id', 'id'])->on('customers')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_measurements');
        Schema::dropIfExists('customer_preferences');
        Schema::dropIfExists('customer_consents');
    }
};
