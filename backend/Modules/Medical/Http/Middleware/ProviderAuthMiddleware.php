<?php

namespace Modules\Medical\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Modules\Medical\Services\Provider\ProviderAuthenticationService;

class ProviderAuthMiddleware
{
    public function __construct(
        private ProviderAuthenticationService $authService
    ) {}

    /**
     * Handle an incoming request.
     *
     * Authenticates the provider using API key and secret from headers.
     * Expected headers:
     * - X-API-Key: The provider's API key
     * - X-API-Secret: The provider's API secret
     *
     * On success, attaches provider and credential to the request.
     */
    public function handle(Request $request, Closure $next, ?string $requiredScope = null): Response
    {
        $startTime = microtime(true);

        // Extract credentials from headers
        $apiKey = $request->header('X-API-Key') ?? $request->header('X-Api-Key');
        $apiSecret = $request->header('X-API-Secret') ?? $request->header('X-Api-Secret');

        // Validate presence of credentials
        if (empty($apiKey) || empty($apiSecret)) {
            return $this->errorResponse(
                'Missing API credentials',
                'MISSING_CREDENTIALS',
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Authenticate
        $result = $this->authService->authenticate(
            $apiKey,
            $apiSecret,
            $request->ip()
        );

        if (!$result->success) {
            return $this->errorResponse(
                $result->errorMessage,
                $result->errorCode,
                $this->getStatusCodeForError($result->errorCode)
            );
        }

        // Validate scope if required
        if ($requiredScope && !$this->authService->hasScope($result->credential, $requiredScope)) {
            return $this->errorResponse(
                "Insufficient permissions. Required scope: {$requiredScope}",
                'INSUFFICIENT_SCOPE',
                Response::HTTP_FORBIDDEN
            );
        }

        // Auto-detect and validate scope from endpoint
        $endpoint = $request->path();
        if (!$this->authService->validateScopeForEndpoint($result->credential, $endpoint)) {
            $requiredScope = $this->authService->getScopeForEndpoint($endpoint);
            return $this->errorResponse(
                "Insufficient permissions for this endpoint. Required scope: {$requiredScope}",
                'INSUFFICIENT_SCOPE',
                Response::HTTP_FORBIDDEN
            );
        }

        // Attach provider and credential to request
        $request->attributes->set('provider', $result->provider);
        $request->attributes->set('provider_credential', $result->credential);
        $request->attributes->set('auth_elapsed_ms', $result->elapsedMs);

        // Set request start time for logging
        $request->attributes->set('request_start_time', $startTime);

        return $next($request);
    }

    /**
     * Map error codes to HTTP status codes
     */
    private function getStatusCodeForError(string $errorCode): int
    {
        return match ($errorCode) {
            'MISSING_CREDENTIALS',
            'INVALID_API_KEY',
            'INVALID_SECRET' => Response::HTTP_UNAUTHORIZED,
            'CREDENTIAL_INACTIVE',
            'CREDENTIAL_EXPIRED',
            'CREDENTIAL_REVOKED',
            'PROVIDER_INACTIVE' => Response::HTTP_FORBIDDEN,
            'IP_NOT_ALLOWED' => Response::HTTP_FORBIDDEN,
            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };
    }

    /**
     * Return standardized error response
     */
    private function errorResponse(string $message, string $code, int $statusCode): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'timestamp' => now()->toIso8601String(),
        ], $statusCode);
    }
}
