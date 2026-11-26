<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Password reset requests (ADR-021).
     *
     * There are no emailed reset links in Ravand. A manager/operator who
     * forgot their password files a request; the owner (and managers for
     * lower roles) approve it in the panel by issuing a temporary password.
     * The request itself is platform-level data, so it is also mirrored to
     * the owner's Bale admin bot when configured.
     */
    public function up(): void
    {
        Schema::create('password_reset_requests', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('user_id', 26)->nullable()->index();
            $table->string('phone', 20);
            $table->string('status')->default('pending')->index();
            $table->string('requested_ip', 45)->nullable();
            $table->string('handled_by', 26)->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('handled_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_requests');
    }
};
