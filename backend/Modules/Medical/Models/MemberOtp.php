<?php

namespace Modules\Medical\Models;

use Illuminate\Support\Str;

class MemberOtp extends BaseModel
{
    protected $table = 'med_member_otps';

    protected $fillable = [
        'email',
        'phone',
        'code',
        'purpose',
        'expires_at',
        'verified_at',
        'attempts',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'attempts' => 'integer',
    ];

    protected $hidden = [
        'code',
    ];

    // OTP Purposes
    const PURPOSE_LOGIN = 'login';
    const PURPOSE_VERIFY_EMAIL = 'verify_email';
    const PURPOSE_VERIFY_PHONE = 'verify_phone';
    const PURPOSE_CONFIRM_ACTION = 'confirm_action';
    const PURPOSE_PASSWORD_RESET = 'password_reset';

    // Configuration
    const CODE_LENGTH = 6;
    const EXPIRY_MINUTES = 10;
    const MAX_ATTEMPTS = 5;
    const RATE_LIMIT_MINUTES = 1; // Minimum time between OTP requests

    /**
     * Generate a new OTP for the given email/purpose.
     */
    public static function generate(
        string $email,
        string $purpose,
        ?string $phone = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        // Invalidate previous OTPs for same email/purpose
        static::where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->delete();

        // Generate secure random code
        $code = str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);

        return static::create([
            'email' => strtolower(trim($email)),
            'phone' => $phone,
            'code' => $code,
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? Str::limit($userAgent, 255) : null,
        ]);
    }

    /**
     * Verify an OTP code.
     */
    public static function verify(string $email, string $code, string $purpose): array
    {
        $otp = static::where('email', strtolower(trim($email)))
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$otp) {
            return [
                'success' => false,
                'error' => 'Invalid or expired code. Please request a new one.',
            ];
        }

        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            $otp->delete();
            return [
                'success' => false,
                'error' => 'Too many failed attempts. Please request a new code.',
            ];
        }

        if ($otp->code !== $code) {
            $otp->increment('attempts');
            $remaining = self::MAX_ATTEMPTS - $otp->attempts;
            return [
                'success' => false,
                'error' => "Invalid code. {$remaining} attempts remaining.",
            ];
        }

        // Mark as verified
        $otp->verified_at = now();
        $otp->save();

        return [
            'success' => true,
            'otp' => $otp,
        ];
    }

    /**
     * Check if rate limited (prevent spam).
     */
    public static function isRateLimited(string $email, string $purpose): bool
    {
        return static::where('email', strtolower(trim($email)))
            ->where('purpose', $purpose)
            ->where('created_at', '>', now()->subMinutes(self::RATE_LIMIT_MINUTES))
            ->exists();
    }

    /**
     * Cleanup expired OTPs.
     */
    public static function cleanupExpired(): int
    {
        return static::where('expires_at', '<', now())->delete();
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeForEmail($query, string $email)
    {
        return $query->where('email', strtolower(trim($email)));
    }

    public function scopeForPurpose($query, string $purpose)
    {
        return $query->where('purpose', $purpose);
    }

    public function scopeValid($query)
    {
        return $query->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->where('attempts', '<', self::MAX_ATTEMPTS);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at < now();
    }

    public function getIsVerifiedAttribute(): bool
    {
        return $this->verified_at !== null;
    }

    public function getRemainingAttemptsAttribute(): int
    {
        return max(0, self::MAX_ATTEMPTS - $this->attempts);
    }
}
