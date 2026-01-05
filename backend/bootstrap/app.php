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
        health: '/up',
        then: function () {
            // Register Medical Module Routes
            Route::middleware('api')
                ->group(base_path('Modules/Medical/Routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register route middleware aliases
        $middleware->alias([
            'module' => \App\Http\Middleware\CheckModuleAccess::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'medical.permission' => \Modules\Medical\Http\Middleware\CheckMedicalPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
