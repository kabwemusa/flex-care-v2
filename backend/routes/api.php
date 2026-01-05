<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ModuleAccessController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Global API routes for IAM (Identity and Access Management)
| Module-specific routes are defined in their respective modules.
|
*/

// =========================================================================
// PUBLIC ROUTES (No Authentication Required)
// =========================================================================

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
});

// =========================================================================
// PROTECTED ROUTES (Authentication Required)
// =========================================================================

Route::middleware('auth:sanctum')->group(function () {

    // Auth Management
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
    });

    // Module Access Management (Admin only)
    Route::prefix('users/{userId}/module-access')->middleware('role:System Administrator')->group(function () {
        Route::get('/', [ModuleAccessController::class, 'index'])->name('module-access.index');
        Route::post('/grant', [ModuleAccessController::class, 'grant'])->name('module-access.grant');
        Route::post('/revoke', [ModuleAccessController::class, 'revoke'])->name('module-access.revoke');
    });

    // =========================================================================
    // USER MANAGEMENT (Admin/User Manager only)
    // =========================================================================
    Route::middleware('role:System Administrator|User Manager')->group(function () {
        // User CRUD
        Route::apiResource('users', UserController::class);

        // User Actions
        Route::post('users/{id}/activate', [UserController::class, 'activate'])->name('users.activate');
        Route::post('users/{id}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        Route::post('users/{id}/roles', [UserController::class, 'assignRoles'])->name('users.assign-roles');
        Route::get('users/{id}/permissions', [UserController::class, 'permissions'])->name('users.permissions');
    });

    // =========================================================================
    // ROLE & PERMISSION MANAGEMENT (Admin only)
    // =========================================================================
    Route::middleware('role:System Administrator')->group(function () {
        // Role CRUD
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{id}', [RoleController::class, 'show'])->name('roles.show');
        Route::patch('roles/{id}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{id}', [RoleController::class, 'destroy'])->name('roles.destroy');

        // Permissions
        Route::get('permissions', [RoleController::class, 'permissions'])->name('permissions.index');
    });
});
