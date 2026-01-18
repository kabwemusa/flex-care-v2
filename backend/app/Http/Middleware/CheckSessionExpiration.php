<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class CheckSessionExpiration
{
    /**
     * Handle an incoming request.
     *
     * Validates token expiration based on:
     * 1. Absolute lifetime (SESSION_LIFETIME) - token expires after X minutes from creation
     * 2. Inactivity timeout (SESSION_INACTIVITY_TIMEOUT) - token expires after X minutes of no activity
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

        // Get configuration values with sensible defaults
        $sessionLifetime = (int) config('password.session.lifetime', 120); // 2 hours default
        $inactivityTimeout = (int) config('password.session.inactivity_timeout', 15); // 15 minutes default

        // Use Carbon for consistent timezone handling
        $now = Carbon::now();
        $tokenCreatedAt = Carbon::parse($token->created_at);
        $tokenLastUsedAt = $token->last_used_at 
            ? Carbon::parse($token->last_used_at) 
            : $tokenCreatedAt;

        // Check 1: Absolute session lifetime (token age)
        $tokenAgeMinutes = $tokenCreatedAt->diffInMinutes($now);
        if ($tokenAgeMinutes >= $sessionLifetime) {
            $token->delete();

            return response()->json([
                'success' => false,
                'message' => 'Session expired. Please login again.',
                'error_code' => 'SESSION_EXPIRED',
                'details' => [
                    'reason' => 'Token exceeded maximum lifetime',
                    'max_lifetime_minutes' => $sessionLifetime,
                    'token_age_minutes' => $tokenAgeMinutes,
                ]
            ], 401);
        }

        // Check 2: Inactivity timeout (time since last activity)
        $inactiveMinutes = $tokenLastUsedAt->diffInMinutes($now);
        if ($inactiveMinutes >= $inactivityTimeout) {
            $token->delete();

            return response()->json([
                'success' => false,
                'message' => 'Session expired due to inactivity. Please login again.',
                'error_code' => 'SESSION_INACTIVE',
                'details' => [
                    'reason' => 'No activity detected',
                    'inactivity_timeout_minutes' => $inactivityTimeout,
                    'inactive_minutes' => $inactiveMinutes,
                ]
            ], 401);
        }

        // Update last_used_at timestamp to track activity
        // Only update if more than 1 minute has passed to reduce DB writes
        if ($inactiveMinutes >= 1) {
            $token->forceFill(['last_used_at' => $now])->save();
        }

        return $next($request);
    }
}
