<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ModuleAccessController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ApprovalGroupController;
use App\Http\Controllers\ApprovalWorkflowController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
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

Route::middleware(['auth:sanctum', 'session.check'])->group(function () {

    // Auth Management
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
    });

    // Password Management
    Route::prefix('password')->group(function () {
        Route::post('/change', [PasswordController::class, 'changePassword'])->name('password.change');
    });

    // Module Access Management (Admin only)
    Route::prefix('users/{userId}/module-access')->middleware('permission:module_access.grant')->group(function () {
        Route::get('/', [ModuleAccessController::class, 'index'])->name('module-access.index');
        Route::post('/grant', [ModuleAccessController::class, 'grant'])->name('module-access.grant');
        Route::post('/revoke', [ModuleAccessController::class, 'revoke'])
            ->name('module-access.revoke')
            ->middleware('permission:module_access.revoke');
    });

    // =========================================================================
    // USER MANAGEMENT (Admin/User Manager only)
    // =========================================================================
    Route::middleware('permission:users.view')->group(function () {
        // User CRUD
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store')->middleware('permission:users.create');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update')->middleware('permission:users.update');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.patch')->middleware('permission:users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy')->middleware('permission:users.delete');

        // User Actions
        Route::post('users/{id}/activate', [UserController::class, 'activate'])->name('users.activate')->middleware('permission:users.activate');
        Route::post('users/{id}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate')->middleware('permission:users.deactivate');
        Route::post('users/{id}/roles', [UserController::class, 'assignRoles'])->name('users.assign-roles')->middleware('permission:roles.assign');
        Route::get('users/{id}/permissions', [UserController::class, 'permissions'])->name('users.permissions');

        // Admin-only password actions
        Route::post('users/{id}/force-password-change', [PasswordController::class, 'forcePasswordChange'])
            ->name('users.force-password-change')
            ->middleware('permission:users.update');
        Route::post('users/{id}/reset-password', [PasswordController::class, 'resetPassword'])
            ->name('users.reset-password')
            ->middleware('permission:users.update');
    });

    // =========================================================================
    // ROLE & PERMISSION MANAGEMENT (Admin only)
    // =========================================================================
    Route::middleware('permission:roles.view')->group(function () {
        // Role CRUD
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store')->middleware('permission:roles.create');
        Route::get('roles/{id}', [RoleController::class, 'show'])->name('roles.show');
        Route::patch('roles/{id}', [RoleController::class, 'update'])->name('roles.update')->middleware('permission:roles.update');
        Route::delete('roles/{id}', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('permission:roles.delete');

        // Permissions
        Route::get('permissions', [RoleController::class, 'permissions'])->name('permissions.index');
    });

    // =========================================================================
    // NOTIFICATIONS (All authenticated users)
    // =========================================================================
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::put('/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
        Route::put('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
        Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
        Route::delete('/read/all', [NotificationController::class, 'deleteAllRead'])->name('notifications.delete-all-read');
    });

    // =========================================================================
    // APPROVALS (All authenticated users)
    // =========================================================================
    Route::prefix('approvals')->group(function () {
        Route::get('/pending', [ApprovalController::class, 'pending'])->name('approvals.pending');
        Route::get('/history', [ApprovalController::class, 'history'])->name('approvals.history');
        Route::get('/{id}', [ApprovalController::class, 'show'])->name('approvals.show');
        Route::post('/{id}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
        Route::post('/{id}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
        Route::post('/{id}/return', [ApprovalController::class, 'returnForAmendment'])->name('approvals.return');
    });

    // =========================================================================
    // REPORTS (All authenticated users - view permissions can be added later)
    // =========================================================================
    Route::prefix('reports')->middleware(['module:medical', 'permission:medical.reports.view'])->group(function () {
        Route::get('/summary', [ReportController::class, 'summary'])->name('reports.summary');
        Route::get('/approvals', [ReportController::class, 'approvalMetrics'])->name('reports.approvals');
        Route::get('/policies', [ReportController::class, 'policyAnalytics'])->name('reports.policies');
        Route::get('/billing', [ReportController::class, 'billingReports'])->name('reports.billing');

        // Export endpoints
        Route::middleware('permission:medical.reports.export')->group(function () {
            Route::get('/export/approvals', [ReportController::class, 'exportApprovals'])->name('reports.export.approvals');
            Route::get('/export/policies', [ReportController::class, 'exportPolicies'])->name('reports.export.policies');
            Route::get('/export/billing', [ReportController::class, 'exportBilling'])->name('reports.export.billing');
        });
    });

    // =========================================================================
    // APPROVAL SETTINGS (Admin only)
    // =========================================================================
    Route::middleware('role:System Administrator')->prefix('settings')->group(function () {
        // Approval Groups
        Route::prefix('approval-groups')->group(function () {
            Route::get('/', [ApprovalGroupController::class, 'index'])->name('approval-groups.index');
            Route::post('/', [ApprovalGroupController::class, 'store'])->name('approval-groups.store');
            Route::get('/{id}', [ApprovalGroupController::class, 'show'])->name('approval-groups.show');
            Route::put('/{id}', [ApprovalGroupController::class, 'update'])->name('approval-groups.update');
            Route::delete('/{id}', [ApprovalGroupController::class, 'destroy'])->name('approval-groups.destroy');
            Route::post('/{id}/members', [ApprovalGroupController::class, 'addMembers'])->name('approval-groups.add-members');
            Route::delete('/{groupId}/members/{userId}', [ApprovalGroupController::class, 'removeMember'])->name('approval-groups.remove-member');
            Route::get('/{id}/available-users', [ApprovalGroupController::class, 'availableUsers'])->name('approval-groups.available-users');
        });

        // Approval Workflows
        Route::prefix('approval-workflows')->group(function () {
            Route::get('/entity-types', [ApprovalWorkflowController::class, 'entityTypes'])->name('approval-workflows.entity-types');
            Route::get('/', [ApprovalWorkflowController::class, 'index'])->name('approval-workflows.index');
            Route::post('/', [ApprovalWorkflowController::class, 'store'])->name('approval-workflows.store');
            Route::get('/{id}', [ApprovalWorkflowController::class, 'show'])->name('approval-workflows.show');
            Route::put('/{id}', [ApprovalWorkflowController::class, 'update'])->name('approval-workflows.update');
            Route::delete('/{id}', [ApprovalWorkflowController::class, 'destroy'])->name('approval-workflows.destroy');
            Route::post('/{workflowId}/steps', [ApprovalWorkflowController::class, 'addStep'])->name('approval-workflows.add-step');
            Route::put('/{workflowId}/steps/{stepId}', [ApprovalWorkflowController::class, 'updateStep'])->name('approval-workflows.update-step');
            Route::delete('/{workflowId}/steps/{stepId}', [ApprovalWorkflowController::class, 'deleteStep'])->name('approval-workflows.delete-step');
            Route::put('/{workflowId}/steps/reorder', [ApprovalWorkflowController::class, 'reorderSteps'])->name('approval-workflows.reorder-steps');
        });
    });
});
