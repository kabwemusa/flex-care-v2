<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSessionExpiration
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
       
        if (!auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();
        $token = $user->currentAccessToken();

        if (!$token) {
            return $next($request);
        }

        // Check token expiration (session lifetime)
        $sessionLifetime = config('password.session.lifetime', 120); // minutes
        $tokenCreatedAt = $token->created_at;

        if (now()->diffInMinutes($tokenCreatedAt) >= $sessionLifetime) {
            // Token has expired
            $token->delete();

            return response()->json([
                'success' => false,
                'message' => 'Session expired. Please login again.',
                'error_code' => 'SESSION_EXPIRED',
            ], 401);
        }

        // Check inactivity timeout
        $inactivityTimeout = config('password.session.inactivity_timeout', 1); // minutes
        $tokenLastUsedAt = $token->last_used_at ?? $tokenCreatedAt;

        if (now()->diffInMinutes($tokenLastUsedAt) >= $inactivityTimeout) {
            // Session inactive for too long
            $token->delete();

            return response()->json([
                'success' => false,
                'message' => 'Session expired due to inactivity. Please login again.',
                'error_code' => 'SESSION_INACTIVE',
            ], 401);
        }

        return $next($request);
    }
}
