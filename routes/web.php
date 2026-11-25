<?php

use App\Http\Controllers\Tenancy\TenantSelectionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Tenant selection: authenticated users with zero or multiple memberships.
// No `tenant` middleware here by design — it is the fallback screen.
Route::middleware(['auth'])->group(function () {
    Route::get('select-tenant', [TenantSelectionController::class, 'select'])
        ->name('tenant.index');

    Route::post('select-tenant', [TenantSelectionController::class, 'switchTenant'])
        ->name('tenant.store');
});

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
