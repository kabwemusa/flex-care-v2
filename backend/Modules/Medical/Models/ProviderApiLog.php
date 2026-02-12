<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderApiLog extends Model
{
    use HasUuids;

    protected $table = 'med_provider_api_logs';

    public $timestamps = false;

    // =========================================================================
    // FILLABLE
    // =========================================================================

    protected $fillable = [
        'provider_id',
        'credential_id',
        'endpoint',
        'method',
        'request_headers',
        'request_payload',
        'request_query',
        'response_status',
        'response_body',
        'response_time_ms',
        'ip_address',
        'user_agent',
        'member_id',
        'claim_id',
        'preauth_id',
        'policy_id',
        'is_error',
        'error_code',
        'error_message',
        'rate_limited',
        'rate_limit_remaining',
        'created_at',
    ];

    protected $casts = [
        'request_headers' => 'array',
        'request_payload' => 'array',
        'response_body' => 'array',
        'response_status' => 'integer',
        'response_time_ms' => 'integer',
        'is_error' => 'boolean',
        'rate_limited' => 'boolean',
        'rate_limit_remaining' => 'integer',
        'created_at' => 'datetime',
    ];

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = $model->created_at ?? now();
        });
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(ProviderApiCredential::class, 'credential_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function preauthorization(): BelongsTo
    {
        return $this->belongsTo(PreAuthorization::class, 'preauth_id');
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeForProvider($query, string $providerId)
    {
        return $query->where('provider_id', $providerId);
    }

    public function scopeForMember($query, string $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    public function scopeErrors($query)
    {
        return $query->where('is_error', true);
    }

    public function scopeRateLimited($query)
    {
        return $query->where('rate_limited', true);
    }

    public function scopeForEndpoint($query, string $endpoint)
    {
        return $query->where('endpoint', $endpoint);
    }

    public function scopeSlowRequests($query, int $thresholdMs = 1000)
    {
        return $query->where('response_time_ms', '>', $thresholdMs);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getIsSuccessAttribute(): bool
    {
        return $this->response_status >= 200 && $this->response_status < 300;
    }

    public function getIsClientErrorAttribute(): bool
    {
        return $this->response_status >= 400 && $this->response_status < 500;
    }

    public function getIsServerErrorAttribute(): bool
    {
        return $this->response_status >= 500;
    }

    public function getResponseTimeSecondsAttribute(): float
    {
        return $this->response_time_ms / 1000;
    }

    // =========================================================================
    // STATIC METHODS
    // =========================================================================

    public static function log(
        string $providerId,
        ?string $credentialId,
        string $endpoint,
        string $method,
        int $responseStatus,
        int $responseTimeMs,
        string $ipAddress,
        array $options = []
    ): self {
        return static::create([
            'provider_id' => $providerId,
            'credential_id' => $credentialId,
            'endpoint' => $endpoint,
            'method' => $method,
            'request_headers' => $options['request_headers'] ?? null,
            'request_payload' => $options['request_payload'] ?? null,
            'request_query' => $options['request_query'] ?? null,
            'response_status' => $responseStatus,
            'response_body' => $options['response_body'] ?? null,
            'response_time_ms' => $responseTimeMs,
            'ip_address' => $ipAddress,
            'user_agent' => $options['user_agent'] ?? null,
            'member_id' => $options['member_id'] ?? null,
            'claim_id' => $options['claim_id'] ?? null,
            'preauth_id' => $options['preauth_id'] ?? null,
            'policy_id' => $options['policy_id'] ?? null,
            'is_error' => $options['is_error'] ?? ($responseStatus >= 400),
            'error_code' => $options['error_code'] ?? null,
            'error_message' => $options['error_message'] ?? null,
            'rate_limited' => $options['rate_limited'] ?? false,
            'rate_limit_remaining' => $options['rate_limit_remaining'] ?? null,
        ]);
    }

    public static function getStatisticsForProvider(string $providerId, int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $stats = static::where('provider_id', $providerId)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                COUNT(*) as total_requests,
                SUM(CASE WHEN is_error = true THEN 1 ELSE 0 END) as error_count,
                SUM(CASE WHEN rate_limited = true THEN 1 ELSE 0 END) as rate_limited_count,
                AVG(response_time_ms) as avg_response_time,
                MAX(response_time_ms) as max_response_time,
                MIN(response_time_ms) as min_response_time
            ')
            ->first();

        $endpointStats = static::where('provider_id', $providerId)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('endpoint, COUNT(*) as count')
            ->groupBy('endpoint')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'endpoint')
            ->toArray();

        return [
            'period_days' => $days,
            'total_requests' => $stats->total_requests ?? 0,
            'error_count' => $stats->error_count ?? 0,
            'error_rate' => $stats->total_requests > 0
                ? round(($stats->error_count / $stats->total_requests) * 100, 2)
                : 0,
            'rate_limited_count' => $stats->rate_limited_count ?? 0,
            'avg_response_time_ms' => round($stats->avg_response_time ?? 0, 2),
            'max_response_time_ms' => $stats->max_response_time ?? 0,
            'min_response_time_ms' => $stats->min_response_time ?? 0,
            'endpoints' => $endpointStats,
        ];
    }
}
