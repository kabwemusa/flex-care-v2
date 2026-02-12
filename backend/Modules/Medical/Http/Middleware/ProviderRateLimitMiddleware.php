<?php

namespace Modules\Medical\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Modules\Medical\Services\Provider\ProviderAuthenticationService;

class ProviderRateLimitMiddleware
{
    public function __construct(
        private ProviderAuthenticationService $authService
    ) {}

    /**
     * Handle an incoming request.
     *
     * Checks rate limits for the authenticated provider.
     * Must run AFTER ProviderAuthMiddleware.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $credential = $request->attributes->get('provider_credential');

        if (!$credential) {
            // No credential attached, skip rate limiting
            // This shouldn't happen if middleware is ordered correctly
            return $next($request);
        }

        // Check rate limits
        $rateLimitResult = $this->authService->checkRateLimit($credential);

        if (!$rateLimitResult->allowed) {
            return $this->rateLimitedResponse($rateLimitResult);
        }

        // Increment rate limit counters
        $this->authService->incrementRateLimits($credential);

        // Add rate limit headers to response
        $response = $next($request);

        // Recheck after increment for accurate remaining counts
        $updatedResult = $this->authService->checkRateLimit($credential);

        foreach ($updatedResult->getHeaders() as $header => $value) {
            $response->headers->set($header, (string) $value);
        }

        return $response;
    }

    /**
     * Return rate limited response
     */
    private function rateLimitedResponse($rateLimitResult): Response
    {
        $response = response()->json([
            'success' => false,
            'error' => [
                'code' => 'RATE_LIMIT_EXCEEDED',
                'message' => "Rate limit exceeded. Please retry after {$rateLimitResult->retryAfter} seconds.",
                'limited_by' => $rateLimitResult->limitedBy,
                'retry_after' => $rateLimitResult->retryAfter,
            ],
            'limits' => $rateLimitResult->limits,
            'timestamp' => now()->toIso8601String(),
        ], Response::HTTP_TOO_MANY_REQUESTS);

        foreach ($rateLimitResult->getHeaders() as $header => $value) {
            $response->headers->set($header, (string) $value);
        }

        return $response;
    }
}
