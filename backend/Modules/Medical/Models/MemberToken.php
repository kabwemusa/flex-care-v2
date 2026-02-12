<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MemberToken extends BaseModel
{
    protected $table = 'med_member_tokens';

    protected $fillable = [
        'member_id',
        'token_hash',
        'device_name',
        'ip_address',
        'user_agent',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'token_hash',
    ];

    // Configuration
    const TOKEN_EXPIRY_DAYS = 30;

    /**
     * Generate a new token for a member.
     * Returns the plain text token (only available once).
     */
    public static function generate(
        Member $member,
        ?string $deviceName = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $plainToken = Str::random(64);
        $tokenHash = hash('sha256', $plainToken);

        $token = static::create([
            'member_id' => $member->id,
            'token_hash' => $tokenHash,
            'device_name' => $deviceName ?? 'Web Browser',
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? Str::limit($userAgent, 255) : null,
            'expires_at' => now()->addDays(self::TOKEN_EXPIRY_DAYS),
        ]);

        return [
            'token' => $plainToken,
            'model' => $token,
            'expires_at' => $token->expires_at,
        ];
    }

    /**
     * Find token by plain text token.
     */
    public static function findByToken(string $plainToken): ?self
    {
        $tokenHash = hash('sha256', $plainToken);

        return static::where('token_hash', $tokenHash)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Validate a token and return the member.
     */
    public static function validateToken(string $plainToken): ?Member
    {
        $token = static::findByToken($plainToken);

        if (!$token) {
            return null;
        }

        // Update last used timestamp
        $token->last_used_at = now();
        $token->save();

        return $token->member;
    }

    /**
     * Revoke all tokens for a member.
     */
    public static function revokeAllForMember(string $memberId): int
    {
        return static::where('member_id', $memberId)->delete();
    }

    /**
     * Cleanup expired tokens.
     */
    public static function cleanupExpired(): int
    {
        return static::where('expires_at', '<', now())->delete();
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeForMember($query, string $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at < now();
    }

    public function getIsActiveAttribute(): bool
    {
        return !$this->is_expired;
    }
}
