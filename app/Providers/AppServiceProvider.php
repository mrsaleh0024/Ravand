<?php

namespace App\Providers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
     * centrally in TenantRole (spec 8.2).
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
