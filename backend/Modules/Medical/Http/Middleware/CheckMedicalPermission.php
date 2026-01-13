<?php

namespace Modules\Medical\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMedicalPermission
{
    /**
     * Handle an incoming request.
     *
     * Check if the authenticated user has the specified Medical permission.
     *
     * Usage: Route::middleware(['auth:sanctum', 'module:medical', 'medical.permission:medical.policies.view'])
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission  The permission to check (e.g., 'medical.policies.view')
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // System admins bypass all permission checks
        if ($user->is_system_admin) {
            return $next($request);
        }

        // Check if user has the specific permission
        if (!$user->hasPermissionTo($permission, 'medical')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You do not have permission to perform this action.',
                'required_permission' => $permission,
            ], 403);
        }

        return $next($request);
    }
}
