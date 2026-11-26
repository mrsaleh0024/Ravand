<?php

use App\Http\Controllers\Settings\AuditLogController;
use App\Http\Controllers\Settings\BaleSettingsController;
use App\Http\Controllers\Settings\LoyaltySettingsController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\TenantSettingsController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

// The force-password screen is excluded from `password.changed` (routes/web.php);
// everything else below requires a settled password.
Route::middleware(['auth', 'password.changed'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    // Owner Bale settings: chat id + test message (ADR-020).
    Route::put('settings/bale', [BaleSettingsController::class, 'update'])
        ->middleware('can:panel.bale.manage')
        ->name('settings.bale.update');

    Route::post('settings/bale/test', [BaleSettingsController::class, 'test'])
        ->middleware('can:panel.bale.manage')
        ->name('settings.bale.test');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    // Tenant-scoped settings: context resolved by `tenant` middleware; abilities
    // checked server-side per role (owner/manager read, owner-only write).
    Route::middleware('tenant')->group(function () {
        Route::get('settings/tenant', [TenantSettingsController::class, 'edit'])
            ->middleware('can:tenant.settings.view')
            ->name('tenant.settings.edit');

        Route::put('settings/tenant', [TenantSettingsController::class, 'update'])
            ->middleware('can:tenant.settings.update')
            ->name('tenant.settings.update');

        Route::get('settings/audit-log', [AuditLogController::class, 'index'])
            ->middleware('can:tenant.audit.view')
            ->name('tenant.audit.index');

        // Loyalty club settings (phase 04) — owner/manager.
        Route::get('settings/loyalty', [LoyaltySettingsController::class, 'edit'])
            ->middleware('can:loyalty.manage')
            ->name('loyalty.settings.edit');

        Route::put('settings/loyalty', [LoyaltySettingsController::class, 'update'])
            ->middleware('can:loyalty.manage')
            ->name('loyalty.settings.update');
    });
});
