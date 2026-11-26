<?php

use App\Http\Controllers\Auth\ForgotPasswordRequestController;
use App\Http\Controllers\Auth\OtpChallengeController;
use App\Http\Controllers\Customers\CustomerAddressController;
use App\Http\Controllers\Customers\CustomerConsentController;
use App\Http\Controllers\Customers\CustomerContactController;
use App\Http\Controllers\Customers\CustomerController;
use App\Http\Controllers\Customers\CustomerInteractionController;
use App\Http\Controllers\Customers\CustomerMeasurementController;
use App\Http\Controllers\Customers\CustomerNoteController;
use App\Http\Controllers\Customers\CustomerPreferenceController;
use App\Http\Controllers\Customers\CustomerSocialAccountController;
use App\Http\Controllers\Customers\CustomerTagController;
use App\Http\Controllers\Customers\TagController;
use App\Http\Controllers\Panel\LiveController;
use App\Http\Controllers\Panel\MemberController;
use App\Http\Controllers\Panel\ResetRequestController;
use App\Http\Controllers\Settings\ForcePasswordChangeController;
use App\Http\Controllers\Tenancy\TenantSelectionController;
use App\Http\Controllers\Webhook\BaleWebhookController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Bale bot webhook (servers with a public URL). Authenticated with the
// shared secret header; CSRF is exempted for this path (ADR-020).
Route::post('webhooks/bale/{bot}', BaleWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('webhooks.bale');

// --- Guest flows -----------------------------------------------------------

Route::middleware(['guest'])->group(function () {
    // Owner second factor: 6-digit code sent through the Bale `code` bot.
    Route::get('otp-challenge', [OtpChallengeController::class, 'show'])->name('otp.challenge');
    Route::post('otp-challenge', [OtpChallengeController::class, 'verify'])
        ->middleware('throttle:otp')
        ->name('otp.verify');
    Route::post('otp-challenge/resend', [OtpChallengeController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('otp.resend');

    // Password reset REQUESTS (ADR-021): no emailed links; the request is
    // stored for panel handling and mirrored to the owner's Bale admin bot.
    Route::get('forgot-password', [ForgotPasswordRequestController::class, 'show'])
        ->name('password.request');
    Route::post('forgot-password', [ForgotPasswordRequestController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.request.store');
});

// --- Authenticated, pending password change --------------------------------

Route::middleware(['auth', 'password.changed'])->group(function () {
    // Tenant selection: users with zero or multiple memberships.
    // No `tenant` middleware here by design — it is the fallback screen.
    Route::get('select-tenant', [TenantSelectionController::class, 'select'])
        ->name('tenant.index');

    Route::post('select-tenant', [TenantSelectionController::class, 'switchTenant'])
        ->name('tenant.store');

    Route::middleware('tenant')->group(function () {
        Route::inertia('dashboard', 'Dashboard')->name('dashboard');

        // Live dashboard data (10s polling) — owner/manager only.
        Route::get('panel/live', LiveController::class)
            ->middleware('can:panel.reset-requests.view')
            ->name('panel.live');

        // Member management.
        Route::get('panel/members', [MemberController::class, 'index'])
            ->middleware('can:panel.members.view')
            ->name('panel.members.index');

        Route::post('panel/members', [MemberController::class, 'store'])
            ->middleware('can:panel.members.create-operator')
            ->name('panel.members.store');

        Route::patch('panel/members/{member}/toggle', [MemberController::class, 'toggle'])
            ->middleware('can:panel.members.manage')
            ->name('panel.members.toggle');

        Route::delete('panel/members/{member}', [MemberController::class, 'destroy'])
            ->middleware('can:panel.members.manage')
            ->name('panel.members.destroy');

        Route::post('panel/members/{member}/reset-password', [MemberController::class, 'resetPassword'])
            ->middleware('can:panel.members.reset-password')
            ->name('panel.members.reset-password');

        // Password reset request handling.
        Route::get('panel/reset-requests', [ResetRequestController::class, 'index'])
            ->middleware('can:panel.reset-requests.view')
            ->name('panel.reset-requests.index');

        Route::post('panel/reset-requests/{resetRequest}/approve', [ResetRequestController::class, 'approve'])
            ->middleware('can:panel.reset-requests.manage')
            ->name('panel.reset-requests.approve');

        Route::post('panel/reset-requests/{resetRequest}/reject', [ResetRequestController::class, 'reject'])
            ->middleware('can:panel.reset-requests.manage')
            ->name('panel.reset-requests.reject');

        // --- Customers (phase 02, spec 5.2 / 6.5 / 7) ----------------------
        // Every route is tenant-scoped: the {customer} binding resolves only
        // within the current tenant (see AppServiceProvider), and the model
        // itself carries a global tenant scope.

        Route::get('customers', [CustomerController::class, 'index'])
            ->name('customers.index');

        Route::get('customers/duplicates', [CustomerController::class, 'duplicates'])
            ->name('customers.duplicates');

        Route::post('customers', [CustomerController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('customers.store');

        Route::get('customers/{customer}', [CustomerController::class, 'show'])
            ->name('customers.show');

        Route::put('customers/{customer}', [CustomerController::class, 'update'])
            ->name('customers.update');

        Route::patch('customers/{customer}/archive', [CustomerController::class, 'archive'])
            ->middleware('can:customers.archive')
            ->name('customers.archive');

        Route::patch('customers/{customer}/restore', [CustomerController::class, 'restore'])
            ->middleware('can:customers.archive')
            ->name('customers.restore');

        // Contact channels
        Route::post('customers/{customer}/contacts', [CustomerContactController::class, 'store'])
            ->name('customer-contacts.store');
        Route::delete('customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'destroy'])
            ->name('customer-contacts.destroy');

        // Social accounts
        Route::post('customers/{customer}/social-accounts', [CustomerSocialAccountController::class, 'store'])
            ->name('customer-socials.store');
        Route::delete('customers/{customer}/social-accounts/{socialAccount}', [CustomerSocialAccountController::class, 'destroy'])
            ->name('customer-socials.destroy');

        // Addresses
        Route::post('customers/{customer}/addresses', [CustomerAddressController::class, 'store'])
            ->name('customer-addresses.store');
        Route::delete('customers/{customer}/addresses/{address}', [CustomerAddressController::class, 'destroy'])
            ->name('customer-addresses.destroy');

        // Notes
        Route::post('customers/{customer}/notes', [CustomerNoteController::class, 'store'])
            ->name('customer-notes.store');
        Route::delete('customers/{customer}/notes/{note}', [CustomerNoteController::class, 'destroy'])
            ->name('customer-notes.destroy');

        // Interactions
        Route::post('customers/{customer}/interactions', [CustomerInteractionController::class, 'store'])
            ->name('customer-interactions.store');

        // Consents
        Route::post('customers/{customer}/consents', [CustomerConsentController::class, 'store'])
            ->name('customer-consents.store');

        // Preferences & measurements
        Route::post('customers/{customer}/preferences', [CustomerPreferenceController::class, 'store'])
            ->name('customer-preferences.store');
        Route::delete('customers/{customer}/preferences/{preference}', [CustomerPreferenceController::class, 'destroy'])
            ->name('customer-preferences.destroy');

        Route::post('customers/{customer}/measurements', [CustomerMeasurementController::class, 'store'])
            ->name('customer-measurements.store');
        Route::delete('customers/{customer}/measurements/{measurement}', [CustomerMeasurementController::class, 'destroy'])
            ->name('customer-measurements.destroy');

        // Tags (shared tenant vocabulary)
        Route::post('customers/{customer}/tags', [CustomerTagController::class, 'attach'])
            ->name('customer-tags.attach');
        Route::delete('customers/{customer}/tags/{tag}', [CustomerTagController::class, 'detach'])
            ->name('customer-tags.detach');

        Route::post('tags', [TagController::class, 'store'])
            ->middleware('can:tags.manage')
            ->name('tags.store');
    });
});

// Mandatory password change screen — reachable for any authenticated user,
// exempt from the `password.changed` middleware itself.
Route::middleware('auth')->group(function () {
    Route::get('settings/force-password', [ForcePasswordChangeController::class, 'show'])
        ->name('password.force.show');

    Route::put('settings/force-password', [ForcePasswordChangeController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.force.update');
});

require __DIR__.'/settings.php';
