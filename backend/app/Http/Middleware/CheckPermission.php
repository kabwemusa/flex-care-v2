<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permission middleware that checks if user has required permission.
 *
 * Usage in routes:
 * Route::get('/schemes', [SchemeController::class, 'index'])
 *      ->middleware('permission:medical.schemes.view');
 *
 * Route::post('/schemes', [SchemeController::class, 'store'])
 *      ->middleware('permission:medical.schemes.create');
 */
class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        // System admins bypass all permission checks
        if ($user && $user->is_system_admin) {
            return $next($request);
        }

        // Extract guard from module-prefixed permissions. Global permissions use the web guard.
        $firstSegment = explode('.', $permission)[0];
        $guardName = in_array($firstSegment, ['medical', 'life', 'motor', 'travel'])
            ? $firstSegment
            : 'web';

        // Check if guard is valid
        if (!in_array($guardName, ['web', 'medical', 'life', 'motor', 'travel'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid permission format',
            ], 500);
        }

        // Check permission
        if (!$user || !$user->hasPermissionTo($permission, $guardName)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You do not have permission to perform this action.',
                'required_permission' => $permission,
            ], 403);
        }

        return $next($request);
    }
}
