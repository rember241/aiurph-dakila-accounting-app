<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->api(append: [\App\Http\Middleware\ReportBrowserSessionExpiry::class]);

        // Use the application CSRF middleware without exempting login.
        $middleware->replace(
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \App\Http\Middleware\VerifyCsrfToken::class
        );

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'legacy.tenant.api' => \App\Http\Middleware\MarkLegacyTenantApiSurface::class,
            'company.context' => \App\Http\Middleware\ResolveTenantContext::class,
            'company.access' => \App\Http\Middleware\EnsureTenantAccess::class,
            'company.ability' => \App\Http\Middleware\EnsureTenantAbility::class,
            // Legacy aliases remain temporarily for compatibility while routes
            // and internal consumers move to company-first semantics.
            'tenant.context' => \App\Http\Middleware\ResolveTenantContext::class,
            'tenant.access' => \App\Http\Middleware\EnsureTenantAccess::class,
            'tenant.ability' => \App\Http\Middleware\EnsureTenantAbility::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
