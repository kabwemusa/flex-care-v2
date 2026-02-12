<?php

namespace Modules\Medical\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Modules\Medical\Services\Provider\ProviderAuthenticationService;

class ProviderApiLogMiddleware
{
    public function __construct(
        private ProviderAuthenticationService $authService
    ) {}

    /**
     * Handle an incoming request.
     *
     * Logs the API request and response for audit and fraud detection.
     * Should run after ProviderAuthMiddleware.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Process the request
        $response = $next($request);

        // Log the request (async if possible to not block response)
        $this->logRequest($request, $response);

        return $response;
    }

    /**
     * Log the API request
     */
    private function logRequest(Request $request, Response $response): void
    {
        $provider = $request->attributes->get('provider');
        $credential = $request->attributes->get('provider_credential');
        $startTime = $request->attributes->get('request_start_time', microtime(true));

        if (!$provider) {
            return; // Can't log without provider
        }

        $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

        // Get response body (limit size for storage)
        $responseBody = null;
        if ($response instanceof \Illuminate\Http\JsonResponse) {
            $responseBody = $response->getData(true);
            // Limit response body size to prevent huge logs
            if (strlen(json_encode($responseBody)) > 10000) {
                $responseBody = ['_truncated' => true, 'size' => strlen(json_encode($responseBody))];
            }
        }

        // Get request payload (sanitize sensitive data)
        $requestPayload = $this->sanitizePayload($request->all());

        // Limit payload size
        if (strlen(json_encode($requestPayload)) > 10000) {
            $requestPayload = ['_truncated' => true, 'size' => strlen(json_encode($requestPayload))];
        }

        try {
            $this->authService->logRequest(
                $provider,
                $credential,
                $request->path(),
                $request->method(),
                $response->getStatusCode(),
                $responseTimeMs,
                $request->ip(),
                [
                    'request_headers' => $this->getRelevantHeaders($request),
                    'request_payload' => $requestPayload,
                    'request_query' => $request->getQueryString(),
                    'response_body' => $responseBody,
                    'user_agent' => $request->userAgent(),
                    'member_id' => $request->attributes->get('member_id'),
                    'claim_id' => $request->attributes->get('claim_id'),
                    'preauth_id' => $request->attributes->get('preauth_id'),
                    'policy_id' => $request->attributes->get('policy_id'),
                    'is_error' => $response->getStatusCode() >= 400,
                    'rate_limited' => $response->getStatusCode() === 429,
                ]
            );
        } catch (\Exception $e) {
            // Don't let logging errors break the API
            report($e);
        }
    }

    /**
     * Get relevant headers for logging (exclude sensitive ones)
     */
    private function getRelevantHeaders(Request $request): array
    {
        $headers = $request->headers->all();

        // Remove sensitive headers
        unset(
            $headers['x-api-secret'],
            $headers['x-api-key'],
            $headers['authorization'],
            $headers['cookie']
        );

        // Flatten single-value arrays
        return array_map(function ($value) {
            return is_array($value) && count($value) === 1 ? $value[0] : $value;
        }, $headers);
    }

    /**
     * Sanitize payload to remove sensitive data
     */
    private function sanitizePayload(array $payload): array
    {
        $sensitiveKeys = [
            'password',
            'api_secret',
            'secret',
            'token',
            'card_number',
            'cvv',
            'pin',
        ];

        array_walk_recursive($payload, function (&$value, $key) use ($sensitiveKeys) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $value = '[REDACTED]';
            }
        });

        return $payload;
    }
}
