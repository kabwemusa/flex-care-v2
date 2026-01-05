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
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Check if user has the specific permission
        if (!$user->can($permission)) {
            return response()->json([
                'message' => 'You do not have permission to perform this action.',
                'required_permission' => $permission,
            ], 403);
        }

        return $next($request);
    }
}
