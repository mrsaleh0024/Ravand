<?php

namespace App\Providers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Tenancy\TenantContext;
use App\Domain\Tenancy\TenantRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tag;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Fortify;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGates();
        $this->configureAuditEvents();
        $this->configureTenantScopedBindings();
    }

    /**
     * Tenant-scoped route model binding for domain routes (phase 02/03).
     * An id from another tenant (or a request without a resolved tenant
     * context) resolves to 404 — the id alone never grants access
     * (spec 2.2). The models' global tenant scope does the actual filtering.
     */
    protected function configureTenantScopedBindings(): void
    {
        $bindTenantScoped = function (string $parameter, string $modelClass): void {
            Route::bind($parameter, function (string $value) use ($modelClass) {
                $context = app(TenantContext::class);

                abort_unless($context->has(), 404);

                return $modelClass::query()->findOrFail($value);
            });
        };

        $bindTenantScoped('customer', Customer::class);
        $bindTenantScoped('tag', Tag::class);
        $bindTenantScoped('product', Product::class);
        $bindTenantScoped('category', ProductCategory::class);
        $bindTenantScoped('order', Order::class);
        $bindTenantScoped('payment', Payment::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Server-side authorization based on the tenant context role. The context
     * is only filled by the `tenant` middleware; routes using these abilities
     * MUST be registered behind `auth` + `tenant` middleware. Role rules live
     * centrally in TenantRole (spec 8.2). Panel access abilities (ADR-022)
     * follow the same pattern: owner > manager > operator.
     */
    protected function configureGates(): void
    {
        Gate::define('tenant.settings.view', function (User $user): bool {
            $role = app(TenantContext::class)->role();

            return $role !== null && $role->canViewSettings();
        });

        Gate::define('tenant.settings.update', function (User $user): bool {
            $role = app(TenantContext::class)->role();

            return $role !== null && $role->canUpdateSettings();
        });

        Gate::define('tenant.audit.view', function (User $user): bool {
            $role = app(TenantContext::class)->role();

            return $role !== null && $role->canViewAuditLog();
        });

        // --- Panel access control (owner & manager) ---

        $isOwner = fn (): bool => app(TenantContext::class)->role() === TenantRole::Owner;
        $isOwnerOrManager = fn (): bool => in_array(app(TenantContext::class)->role(), [TenantRole::Owner, TenantRole::Manager], true);

        Gate::define('panel.members.view', fn (User $user): bool => $isOwnerOrManager());
        // Only the senior manager may define new managers.
        Gate::define('panel.members.create-manager', fn (User $user): bool => $isOwner());
        // Managers may add operators; owners may add any role.
        Gate::define('panel.members.create-operator', fn (User $user): bool => $isOwnerOrManager());
        // Suspend / remove members — owner only.
        Gate::define('panel.members.manage', fn (User $user): bool => $isOwner());
        // Issue a temporary password for an existing member — owner only.
        Gate::define('panel.members.reset-password', fn (User $user): bool => $isOwner());
        // Reset-request handling (view list + approve/reject) — owner/manager.
        Gate::define('panel.reset-requests.view', fn (User $user): bool => $isOwnerOrManager());
        Gate::define('panel.reset-requests.manage', fn (User $user): bool => $isOwnerOrManager());
        // Bale settings (chat id, test message) — owner only.
        Gate::define('panel.bale.manage', fn (User $user): bool => $user->isOwner());

        // --- Customer CRM (phase 02) ---
        // Archiving/restoring a customer is an owner/manager decision;
        // operators keep create/edit access (spec 8.2).
        Gate::define('customers.archive', function (User $user): bool {
            $role = app(TenantContext::class)->role();

            return $role !== null && $role->canManageMembers();
        });
        // The tenant-wide tag vocabulary is maintained by owner/manager.
        Gate::define('tags.manage', function (User $user): bool {
            $role = app(TenantContext::class)->role();

            return $role !== null && $role->canManageMembers();
        });

        // --- Sales & inventory (phase 03) ------------------------------------
        // Catalog and stock maintenance are staff-level decisions.
        Gate::define('catalog.manage', fn (User $user): bool => $isOwnerOrManager());
        Gate::define('inventory.manage', fn (User $user): bool => $isOwnerOrManager());
        // Cancel/return/status transitions move money and stock back.
        Gate::define('orders.manage', fn (User $user): bool => $isOwnerOrManager());
        // Refunding/voiding recorded payments — owner/manager.
        Gate::define('payments.manage', fn (User $user): bool => $isOwnerOrManager());
        // Viewing orders, recording sales and taking payments stay open to
        // every active role: operators are the data-entry workforce
        // (spec 8.2, «ثبت فروش دستی»).
    }

    /**
     * Audit log for authentication events (spec 8.6): successful and failed
     * logins are recorded without any credential material. Tenant id stays
     * null here because tenant context is not resolved at login time.
     */
    protected function configureAuditEvents(): void
    {
        Event::listen(function (Login $event): void {
            /** @var User $user */
            $user = $event->user;

            $user->forceFill(['last_login_at' => now()])->save();

            app(AuditLogger::class)->log(
                action: 'auth.login',
                tenant: null,
                actor: $user,
                entityType: 'user',
                entityId: $user->getKey(),
                metadata: ['guard' => $event->guard],
            );
        });

        Event::listen(function (Logout $event): void {
            if ($event->user instanceof User) {
                app(AuditLogger::class)->log(
                    action: 'auth.logout',
                    tenant: null,
                    actor: $event->user,
                    entityType: 'user',
                    entityId: $event->user->getKey(),
                    metadata: ['guard' => $event->guard],
                );
            }
        });

        Event::listen(function (Failed $event): void {
            $identifier = $event->credentials[Fortify::username()] ?? null;

            app(AuditLogger::class)->log(
                action: 'auth.login_failed',
                tenant: null,
                actor: $event->user instanceof User ? $event->user : null,
                entityType: 'user',
                entityId: $event->user instanceof User ? $event->user->getKey() : null,
                metadata: [
                    'guard' => $event->guard,
                    'attempted_identifier' => is_string($identifier) ? $identifier : null,
                ],
            );
        });
    }
}
