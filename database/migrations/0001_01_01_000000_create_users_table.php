<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Phase 01 (ADR-004): users use a ULID string primary key. The table is
        // restructured before first release — requires a one-time `migrate:fresh`
        // on existing development databases (no customer data exists yet).
        //
        // Identity (ADR-019): the mobile number IS the login identifier.
        // There is no email and no separate username column.
        Schema::create('users', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('name');
            $table->string('phone', 20)->unique();
            $table->string('status')->default('active');
            $table->string('password');
            $table->timestamp('last_login_at')->nullable();
            // Force a new password after a temp password was set by the panel.
            $table->boolean('must_change_password')->default(false);
            // Bale chat id of the OWNER for OTP / admin notifications (ADR-020).
            $table->string('bale_chat_id', 32)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            // users.id is a ULID string (ADR-004) — keep the column type in sync.
            $table->string('user_id', 26)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};
