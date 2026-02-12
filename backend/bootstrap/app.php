<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            // Register Medical Module Routes
            Route::middleware('api')
                ->group(base_path('Modules/Medical/Routes/api.php'));

            // Register Provider API Routes (for hospitals/clinics)
            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('Modules/Medical/Routes/provider-api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register route middleware aliases
        $middleware->alias([
            'module' => \App\Http\Middleware\CheckModuleAccess::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            // Use custom permission middleware that extracts guard from permission name
            // e.g., 'medical.schemes.view' -> checks against 'medical' guard
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'spatie.permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'medical.permission' => \Modules\Medical\Http\Middleware\CheckMedicalPermission::class,
            'session.check' => \App\Http\Middleware\CheckSessionExpiration::class,

            // Provider API Middleware (for hospital/clinic integration)
            'provider.auth' => \Modules\Medical\Http\Middleware\ProviderAuthMiddleware::class,
            'provider.rate-limit' => \Modules\Medical\Http\Middleware\ProviderRateLimitMiddleware::class,
            'provider.log' => \Modules\Medical\Http\Middleware\ProviderApiLogMiddleware::class,

            // Member Portal Middleware (for customer self-service)
            'member.auth' => \Modules\Medical\Http\Middleware\MemberAuthMiddleware::class,
        ]);

        // Apply session expiration check to all authenticated API routes
        // $middleware->prependToGroup('api', [
        //     \App\Http\Middleware\CheckSessionExpiration::class,
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
