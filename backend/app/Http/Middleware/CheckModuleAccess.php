<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleAccess
{
    /**
     * Handle an incoming request.
     *
     * Check if the authenticated user has access to the specified module.
     *
     * Usage: Route::middleware(['auth:sanctum', 'module:medical'])
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $moduleCode  The module code to check (medical, life, motor, travel, admin)
     */
    public function handle(Request $request, Closure $next, string $moduleCode): Response
    {
        $user = $request->user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Check if user is active
        if (!$user->is_active) {
            return response()->json([
                'message' => 'Your account has been deactivated. Please contact support.',
            ], 403);
        }

        // System admins have access to all modules
        if ($user->is_system_admin) {
            return $next($request);
        }

        // Check if user has access to the requested module
        if (!$user->hasModuleAccess($moduleCode)) {
            return response()->json([
                'message' => "You do not have access to the {$moduleCode} module.",
                'module' => $moduleCode,
            ], 403);
        }

        return $next($request);
    }
}
