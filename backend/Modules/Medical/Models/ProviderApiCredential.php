<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class ProviderApiCredential extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'med_provider_api_credentials';

    // =========================================================================
    // CONSTANTS
    // =========================================================================

    public const SCOPE_ELIGIBILITY = 'eligibility';
    public const SCOPE_PREAUTH = 'preauth';
    public const SCOPE_CLAIMS = 'claims';
    public const SCOPE_MEMBER_LOOKUP = 'member_lookup';

    public const DEFAULT_RATE_LIMIT_MINUTE = 60;
    public const DEFAULT_RATE_LIMIT_HOUR = 1000;
    public const DEFAULT_RATE_LIMIT_DAY = 10000;

    // =========================================================================
    // FILLABLE
    // =========================================================================

    protected $fillable = [
        'provider_id',
        'api_key',
        'api_secret',
        'name',
        'description',
        'allowed_ips',
        'scopes',
        'rate_limit_per_minute',
        'rate_limit_per_hour',
        'rate_limit_per_day',
        'last_used_at',
        'last_used_ip',
        'total_requests',
        'failed_requests',
        'expires_at',
        'is_active',
        'revoked_at',
        'revoked_reason',
        'created_by',
        'revoked_by',
    ];

    protected $casts = [
        'allowed_ips' => 'array',
        'scopes' => 'array',
        'rate_limit_per_minute' => 'integer',
        'rate_limit_per_hour' => 'integer',
        'rate_limit_per_day' => 'integer',
        'last_used_at' => 'datetime',
        'total_requests' => 'integer',
        'failed_requests' => 'integer',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = [
        'api_secret',
    ];

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->api_key)) {
                $model->api_key = static::generateApiKey();
            }

            // Set defaults
            $model->rate_limit_per_minute = $model->rate_limit_per_minute ?? self::DEFAULT_RATE_LIMIT_MINUTE;
            $model->rate_limit_per_hour = $model->rate_limit_per_hour ?? self::DEFAULT_RATE_LIMIT_HOUR;
            $model->rate_limit_per_day = $model->rate_limit_per_day ?? self::DEFAULT_RATE_LIMIT_DAY;
            $model->scopes = $model->scopes ?? [self::SCOPE_ELIGIBILITY];
        });
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProviderApiLog::class, 'credential_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeRevoked($query)
    {
        return $query->whereNotNull('revoked_at');
    }

    public function scopeWithScope($query, string $scope)
    {
        return $query->whereJsonContains('scopes', $scope);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getIsRevokedAttribute(): bool
    {
        return $this->revoked_at !== null;
    }

    public function getIsValidAttribute(): bool
    {
        return $this->is_active && !$this->is_expired && !$this->is_revoked;
    }

    public function getSuccessRateAttribute(): float
    {
        if ($this->total_requests === 0) {
            return 100.0;
        }

        return round((($this->total_requests - $this->failed_requests) / $this->total_requests) * 100, 2);
    }

    public function getMaskedApiKeyAttribute(): string
    {
        if (strlen($this->api_key) < 8) {
            return str_repeat('*', strlen($this->api_key));
        }

        return substr($this->api_key, 0, 4) . str_repeat('*', strlen($this->api_key) - 8) . substr($this->api_key, -4);
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public static function generateApiKey(): string
    {
        return 'fc_' . Str::random(32);
    }

    public static function generateApiSecret(): string
    {
        return Str::random(64);
    }

    public function createWithSecret(): array
    {
        $secret = self::generateApiSecret();
        $this->api_secret = Hash::make($secret);
        $this->save();

        return [
            'api_key' => $this->api_key,
            'api_secret' => $secret, // Return plain secret only once
        ];
    }

    public function verifySecret(string $secret): bool
    {
        return Hash::check($secret, $this->api_secret);
    }

    public function regenerateSecret(): string
    {
        $secret = self::generateApiSecret();
        $this->api_secret = Hash::make($secret);
        $this->save();

        return $secret;
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? []);
    }

    public function hasAnyScope(array $scopes): bool
    {
        return !empty(array_intersect($scopes, $this->scopes ?? []));
    }

    public function isIpAllowed(?string $ip): bool
    {
        if (empty($this->allowed_ips) || $ip === null) {
            return true; // No IP restriction
        }

        return in_array($ip, $this->allowed_ips);
    }

    public function recordUsage(string $ip): void
    {
        $this->increment('total_requests');
        $this->update([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
        ]);
    }

    public function recordFailure(): void
    {
        $this->increment('failed_requests');
    }

    public function revoke(string $reason, ?string $revokedBy = null): void
    {
        $this->update([
            'is_active' => false,
            'revoked_at' => now(),
            'revoked_reason' => $reason,
            'revoked_by' => $revokedBy,
        ]);
    }

    public function reactivate(): void
    {
        $this->update([
            'is_active' => true,
            'revoked_at' => null,
            'revoked_reason' => null,
            'revoked_by' => null,
        ]);
    }

    public function extendExpiry(\DateTimeInterface $newExpiry): void
    {
        $this->update(['expires_at' => $newExpiry]);
    }

    public function getRateLimitKey(string $period): string
    {
        return "provider_rate_limit:{$this->id}:{$period}";
    }
}
