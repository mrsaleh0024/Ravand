<?php

use App\Http\Controllers\Settings\AuditLogController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\TenantSettingsController;
/* @chisel-password-confirmation */
use Illuminate\Auth\Middleware\RequirePassword;
/* @end-chisel-password-confirmation */
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        /* @chisel-password-confirmation */
        ->middleware(RequirePassword::class)
        /* @end-chisel-password-confirmation */
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});

// Tenant-scoped settings: context resolved by `tenant` middleware; abilities
// checked server-side per role (owner/manager read, owner-only write).
Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('settings/tenant', [TenantSettingsController::class, 'edit'])
        ->middleware('can:tenant.settings.view')
        ->name('tenant.settings.edit');

    Route::put('settings/tenant', [TenantSettingsController::class, 'update'])
        ->middleware('can:tenant.settings.update')
        ->name('tenant.settings.update');

    Route::get('settings/audit-log', [AuditLogController::class, 'index'])
        ->middleware('can:tenant.audit.view')
        ->name('tenant.audit.index');
});

/* @chisel-passkeys */
Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
/* @end-chisel-passkeys */
