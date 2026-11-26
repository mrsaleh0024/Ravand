<?php

use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Tenant resolution from verified membership (phase 01, spec 2.2).
        $middleware->alias([
            'tenant' => EnsureTenantContext::class,
            // Mandatory password change after a temporary password (ADR-021).
            'password.changed' => EnsurePasswordChanged::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            // Bale bot webhook: authenticated via the shared secret header
            // instead of the CSRF token (ADR-020).
            'webhooks/bale/*',
        ]);

        // CRITICAL ordering rule (phase 02): route model bindings must be
        // substituted only AFTER the tenant context middleware has run, so
        // tenant-scoped bindings (`{customer}`, `{tag}`) resolve inside the
        // current tenant. Without this, SubstituteBindings would resolve the
        // id before EnsureTenantContext filled the context — leaking rows of
        // other tenants (spec 2.2).
        $middleware->priority([
            HandlePrecognitiveRequests::class,
            EncryptCookies::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            AuthenticatesRequests::class,
            ThrottleRequests::class,
            EnsureTenantContext::class,
            SubstituteBindings::class,
            Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('webhooks/*') || $request->expectsJson(),
        );
    })->create();
