<?php

namespace Modules\Medical\Services\Provider;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Medical\Models\Provider;
use Modules\Medical\Models\ProviderApiCredential;
use Modules\Medical\Models\ProviderApiLog;

class ProviderAuthenticationService
{
    // =========================================================================
    // CONSTANTS
    // =========================================================================

    private const CACHE_TTL_SECONDS = 300; // 5 minutes
    private const RATE_LIMIT_CACHE_TTL = 60; // 1 minute for rate limit counters

    // =========================================================================
    // AUTHENTICATION
    // =========================================================================

    /**
     * Authenticate provider using API key and secret
     * Target: < 50ms for cached credentials
     */
    public function authenticate(string $apiKey, string $apiSecret, ?string $ipAddress = null): AuthenticationResult
    {
        $startTime = microtime(true);

        try {
            // Try to get credential from cache first
            $credential = $this->getCachedCredential($apiKey);

            if (!$credential) {
                // Fetch from database
                $credential = ProviderApiCredential::with('provider')
                    ->where('api_key', $apiKey)
                    ->first();

                if ($credential) {
                    $this->cacheCredential($credential);
                }
            }

            // Credential not found
            if (!$credential) {
                return $this->failedResult('Invalid API key', 'INVALID_API_KEY', $startTime);
            }

            // Verify secret
            if (!$credential->verifySecret($apiSecret)) {
                $credential->recordFailure();
                return $this->failedResult('Invalid API secret', 'INVALID_SECRET', $startTime);
            }

            // Check if credential is active
            if (!$credential->is_active) {
                return $this->failedResult('API credential is inactive', 'CREDENTIAL_INACTIVE', $startTime);
            }

            // Check if expired
            if ($credential->is_expired) {
                return $this->failedResult('API credential has expired', 'CREDENTIAL_EXPIRED', $startTime);
            }

            // Check if revoked
            if ($credential->is_revoked) {
                return $this->failedResult('API credential has been revoked', 'CREDENTIAL_REVOKED', $startTime);
            }

            // Check IP restriction
            if (!$credential->isIpAllowed($ipAddress)) {
                return $this->failedResult('IP address not allowed', 'IP_NOT_ALLOWED', $startTime);
            }

            // Check provider status
            $provider = $credential->provider;
            if (!$provider || $provider->status !== Provider::STATUS_ACTIVE) {
                return $this->failedResult('Provider is not active', 'PROVIDER_INACTIVE', $startTime);
            }

            // Record successful authentication
            $credential->recordUsage($ipAddress ?? 'unknown');

            $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);

            return new AuthenticationResult(
                success: true,
                provider: $provider,
                credential: $credential,
                elapsedMs: $elapsedMs
            );

        } catch (\Exception $e) {
            Log::error('Provider authentication error', [
                'api_key' => substr($apiKey, 0, 8) . '...',
                'error' => $e->getMessage(),
            ]);

            return $this->failedResult('Authentication error', 'INTERNAL_ERROR', $startTime);
        }
    }

    /**
     * Validate API key exists (quick check without secret verification)
     */
    public function validateApiKey(string $apiKey): ?ProviderApiCredential
    {
        return $this->getCachedCredential($apiKey)
            ?? ProviderApiCredential::where('api_key', $apiKey)->first();
    }

    // =========================================================================
    // RATE LIMITING
    // =========================================================================

    /**
     * Check if request is within rate limits
     */
    public function checkRateLimit(ProviderApiCredential $credential): RateLimitResult
    {
        $now = now();
        $results = [];

        // Check per-minute limit
        $minuteKey = $credential->getRateLimitKey('minute:' . $now->format('Y-m-d-H-i'));
        $minuteCount = (int) Cache::get($minuteKey, 0);
        $results['minute'] = [
            'limit' => $credential->rate_limit_per_minute,
            'used' => $minuteCount,
            'remaining' => max(0, $credential->rate_limit_per_minute - $minuteCount),
        ];

        // Check per-hour limit
        $hourKey = $credential->getRateLimitKey('hour:' . $now->format('Y-m-d-H'));
        $hourCount = (int) Cache::get($hourKey, 0);
        $results['hour'] = [
            'limit' => $credential->rate_limit_per_hour,
            'used' => $hourCount,
            'remaining' => max(0, $credential->rate_limit_per_hour - $hourCount),
        ];

        // Check per-day limit
        $dayKey = $credential->getRateLimitKey('day:' . $now->format('Y-m-d'));
        $dayCount = (int) Cache::get($dayKey, 0);
        $results['day'] = [
            'limit' => $credential->rate_limit_per_day,
            'used' => $dayCount,
            'remaining' => max(0, $credential->rate_limit_per_day - $dayCount),
        ];

        // Determine if rate limited
        $isLimited = $minuteCount >= $credential->rate_limit_per_minute
            || $hourCount >= $credential->rate_limit_per_hour
            || $dayCount >= $credential->rate_limit_per_day;

        // Find the limiting factor
        $limitedBy = null;
        $retryAfter = null;

        if ($minuteCount >= $credential->rate_limit_per_minute) {
            $limitedBy = 'minute';
            $retryAfter = 60 - $now->second;
        } elseif ($hourCount >= $credential->rate_limit_per_hour) {
            $limitedBy = 'hour';
            $retryAfter = 3600 - ($now->minute * 60 + $now->second);
        } elseif ($dayCount >= $credential->rate_limit_per_day) {
            $limitedBy = 'day';
            $retryAfter = 86400 - ($now->hour * 3600 + $now->minute * 60 + $now->second);
        }

        return new RateLimitResult(
            allowed: !$isLimited,
            limits: $results,
            limitedBy: $limitedBy,
            retryAfter: $retryAfter
        );
    }

    /**
     * Increment rate limit counters
     */
    public function incrementRateLimits(ProviderApiCredential $credential): void
    {
        $now = now();

        // Increment minute counter
        $minuteKey = $credential->getRateLimitKey('minute:' . $now->format('Y-m-d-H-i'));
        Cache::increment($minuteKey);
        Cache::put($minuteKey, Cache::get($minuteKey, 1), 120); // TTL: 2 minutes

        // Increment hour counter
        $hourKey = $credential->getRateLimitKey('hour:' . $now->format('Y-m-d-H'));
        Cache::increment($hourKey);
        Cache::put($hourKey, Cache::get($hourKey, 1), 7200); // TTL: 2 hours

        // Increment day counter
        $dayKey = $credential->getRateLimitKey('day:' . $now->format('Y-m-d'));
        Cache::increment($dayKey);
        Cache::put($dayKey, Cache::get($dayKey, 1), 172800); // TTL: 2 days
    }

    // =========================================================================
    // SCOPE VALIDATION
    // =========================================================================

    /**
     * Check if credential has required scope
     */
    public function hasScope(ProviderApiCredential $credential, string $scope): bool
    {
        return $credential->hasScope($scope);
    }

    /**
     * Check if credential has any of the required scopes
     */
    public function hasAnyScope(ProviderApiCredential $credential, array $scopes): bool
    {
        return $credential->hasAnyScope($scopes);
    }

    /**
     * Validate scope for endpoint
     */
    public function validateScopeForEndpoint(ProviderApiCredential $credential, string $endpoint): bool
    {
        $requiredScope = $this->getScopeForEndpoint($endpoint);

        if ($requiredScope === null) {
            return true; // No specific scope required
        }

        return $credential->hasScope($requiredScope);
    }

    /**
     * Get required scope for an endpoint
     */
    public function getScopeForEndpoint(string $endpoint): ?string
    {
        $scopeMap = [
            '/eligibility/' => ProviderApiCredential::SCOPE_ELIGIBILITY,
            '/preauth/' => ProviderApiCredential::SCOPE_PREAUTH,
            '/claims/' => ProviderApiCredential::SCOPE_CLAIMS,
            '/member/' => ProviderApiCredential::SCOPE_MEMBER_LOOKUP,
        ];

        foreach ($scopeMap as $pattern => $scope) {
            if (str_contains($endpoint, $pattern)) {
                return $scope;
            }
        }

        return null;
    }

    // =========================================================================
    // LOGGING
    // =========================================================================

    /**
     * Log API request
     */
    public function logRequest(
        Provider $provider,
        ?ProviderApiCredential $credential,
        string $endpoint,
        string $method,
        int $responseStatus,
        int $responseTimeMs,
        string $ipAddress,
        array $options = []
    ): ProviderApiLog {
        return ProviderApiLog::log(
            $provider->id,
            $credential?->id,
            $endpoint,
            $method,
            $responseStatus,
            $responseTimeMs,
            $ipAddress,
            $options
        );
    }

    // =========================================================================
    // CREDENTIAL MANAGEMENT
    // =========================================================================

    /**
     * Create new API credentials for a provider
     */
    public function createCredentials(
        Provider $provider,
        string $name,
        array $scopes = [],
        array $options = []
    ): array {
        $credential = new ProviderApiCredential([
            'provider_id' => $provider->id,
            'name' => $name,
            'description' => $options['description'] ?? null,
            'scopes' => $scopes ?: [ProviderApiCredential::SCOPE_ELIGIBILITY],
            'allowed_ips' => $options['allowed_ips'] ?? null,
            'rate_limit_per_minute' => $options['rate_limit_per_minute'] ?? null,
            'rate_limit_per_hour' => $options['rate_limit_per_hour'] ?? null,
            'rate_limit_per_day' => $options['rate_limit_per_day'] ?? null,
            'expires_at' => $options['expires_at'] ?? null,
            'created_by' => $options['created_by'] ?? null,
        ]);

        $secrets = $credential->createWithSecret();

        return [
            'credential' => $credential->fresh(),
            'api_key' => $secrets['api_key'],
            'api_secret' => $secrets['api_secret'],
        ];
    }

    /**
     * Regenerate API secret for a credential
     */
    public function regenerateSecret(ProviderApiCredential $credential): string
    {
        $this->invalidateCredentialCache($credential->api_key);
        return $credential->regenerateSecret();
    }

    /**
     * Revoke a credential
     */
    public function revokeCredential(ProviderApiCredential $credential, string $reason, ?string $revokedBy = null): void
    {
        $this->invalidateCredentialCache($credential->api_key);
        $credential->revoke($reason, $revokedBy);
    }

    // =========================================================================
    // CACHING
    // =========================================================================

    private function getCachedCredential(string $apiKey): ?ProviderApiCredential
    {
        $cacheKey = $this->getCredentialCacheKey($apiKey);
        $cached = Cache::get($cacheKey);

        if ($cached) {
            return ProviderApiCredential::with('provider')->find($cached);
        }

        return null;
    }

    private function cacheCredential(ProviderApiCredential $credential): void
    {
        $cacheKey = $this->getCredentialCacheKey($credential->api_key);
        Cache::put($cacheKey, $credential->id, self::CACHE_TTL_SECONDS);
    }

    private function invalidateCredentialCache(string $apiKey): void
    {
        $cacheKey = $this->getCredentialCacheKey($apiKey);
        Cache::forget($cacheKey);
    }

    private function getCredentialCacheKey(string $apiKey): string
    {
        return "provider_credential:" . hash('sha256', $apiKey);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function failedResult(string $message, string $code, float $startTime): AuthenticationResult
    {
        $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);

        return new AuthenticationResult(
            success: false,
            errorMessage: $message,
            errorCode: $code,
            elapsedMs: $elapsedMs
        );
    }
}

// =========================================================================
// DATA TRANSFER OBJECTS
// =========================================================================

class AuthenticationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?Provider $provider = null,
        public readonly ?ProviderApiCredential $credential = null,
        public readonly ?string $errorMessage = null,
        public readonly ?string $errorCode = null,
        public readonly int $elapsedMs = 0,
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'provider_id' => $this->provider?->id,
            'credential_id' => $this->credential?->id,
            'error_message' => $this->errorMessage,
            'error_code' => $this->errorCode,
            'elapsed_ms' => $this->elapsedMs,
        ];
    }
}

class RateLimitResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly array $limits,
        public readonly ?string $limitedBy = null,
        public readonly ?int $retryAfter = null,
    ) {}

    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'limits' => $this->limits,
            'limited_by' => $this->limitedBy,
            'retry_after' => $this->retryAfter,
        ];
    }

    public function getHeaders(): array
    {
        $headers = [
            'X-RateLimit-Limit-Minute' => $this->limits['minute']['limit'] ?? 0,
            'X-RateLimit-Remaining-Minute' => $this->limits['minute']['remaining'] ?? 0,
            'X-RateLimit-Limit-Hour' => $this->limits['hour']['limit'] ?? 0,
            'X-RateLimit-Remaining-Hour' => $this->limits['hour']['remaining'] ?? 0,
        ];

        if ($this->retryAfter) {
            $headers['Retry-After'] = $this->retryAfter;
        }

        return $headers;
    }
}
