<?php

namespace Modules\Medical\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Modules\Medical\Models\MemberToken;

class MemberAuthMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication required',
            ], 401);
        }

        $member = MemberToken::validateToken($token);

        if (!$member) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or expired token',
            ], 401);
        }

        // Check member is still active
        if (!$member->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Account is not active',
            ], 403);
        }

        // Check portal access is still enabled
        if (!$member->has_portal_access) {
            return response()->json([
                'status' => 'error',
                'message' => 'Portal access is disabled',
            ], 403);
        }

        // Attach member to request for use in controllers
        $request->attributes->set('member', $member);
        $request->attributes->set('member_token', $token);

        return $next($request);
    }

    /**
     * Extract bearer token from request.
     */
    protected function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }
}
