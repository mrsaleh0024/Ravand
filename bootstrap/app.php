<?php

use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('webhooks/*') || $request->expectsJson(),
        );
    })->create();
