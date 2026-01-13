<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use OwenIt\Auditing\Contracts\Auditable;

class User extends Authenticatable implements Auditable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles, HasUuids, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'username',
        'password',
        'mfa_enabled',
        'mfa_secret',
        'is_active',
        'is_system_admin',
        'last_login_at',
        'last_login_ip',
        'password_changed_at',
        'password_expires_at',
        'force_password_change',
        'failed_login_attempts',
        'locked_until',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mfa_enabled' => 'boolean',
            'is_active' => 'boolean',
            'is_system_admin' => 'boolean',
            'force_password_change' => 'boolean',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'password_expires_at' => 'datetime',
            'locked_until' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the modules this user has access to
     */
    public function moduleAccess()
    {
        return $this->hasMany(UserModuleAccess::class);
    }

    /**
     * Get active module access only
     */
    public function activeModules()
    {
        return $this->moduleAccess()->where('is_active', true);
    }

    /**
     * Get the password history for this user
     */
    public function passwordHistories()
    {
        return $this->hasMany(PasswordHistory::class);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope: Active users only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: System admins
     */
    public function scopeSystemAdmins($query)
    {
        return $query->where('is_system_admin', true);
    }

    /**
     * Scope: Users with specific module access
     */
    public function scopeHasModuleAccess($query, string $moduleCode)
    {
        return $query->whereHas('activeModules', function ($q) use ($moduleCode) {
            $q->where('module_code', $moduleCode);
        });
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Check if user has access to a specific module
     */
    public function hasModuleAccess(string $moduleCode): bool
    {
        if ($this->is_system_admin) {
            return true; // System admins have access to all modules
        }

        return $this->activeModules()->where('module_code', $moduleCode)->exists();
    }

    /**
     * Get all module codes this user can access
     */
    public function getModuleCodes(): array
    {
        if ($this->is_system_admin) {
            return ['medical', 'life', 'motor', 'travel', 'admin'];
        }

        return $this->activeModules()->pluck('module_code')->toArray();
    }

    /**
     * Record login activity
     */
    public function recordLogin(string $ipAddress): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
        ]);
    }

    /**
     * Check if MFA is enabled and configured
     */
    public function mfaConfigured(): bool
    {
        return $this->mfa_enabled && !empty($this->mfa_secret);
    }

    // =========================================================================
    // PASSWORD MANAGEMENT METHODS
    // =========================================================================

    /**
     * Check if password has expired
     */
    public function passwordExpired(): bool
    {
        if (!config('password.expiration.enabled')) {
            return false;
        }

        if (!$this->password_expires_at) {
            return false;
        }

        return now()->isAfter($this->password_expires_at);
    }

    /**
     * Check if password is expiring soon
     */
    public function passwordExpiringSoon(): bool
    {
        if (!config('password.expiration.enabled')) {
            return false;
        }

        if (!$this->password_expires_at) {
            return false;
        }

        $warningDays = config('password.expiration.warning_days', 7);
        $warningDate = now()->addDays($warningDays);

        return $this->password_expires_at->isBefore($warningDate) && !$this->passwordExpired();
    }

    /**
     * Get days until password expires
     */
    public function daysUntilPasswordExpires(): ?int
    {
        if (!$this->password_expires_at) {
            return null;
        }

        return now()->diffInDays($this->password_expires_at, false);
    }

    /**
     * Check if account is locked
     */
    public function isLocked(): bool
    {
        if (!$this->locked_until) {
            return false;
        }

        if (now()->isAfter($this->locked_until)) {
            // Lock period has expired, reset
            $this->update([
                'locked_until' => null,
                'failed_login_attempts' => 0,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Increment failed login attempts and lock if necessary
     */
    public function incrementFailedLoginAttempts(): void
    {
        if (!config('password.lockout.enabled')) {
            return;
        }

        $this->increment('failed_login_attempts');

        $maxAttempts = config('password.lockout.max_attempts', 5);
        if ($this->failed_login_attempts >= $maxAttempts) {
            $lockoutMinutes = config('password.lockout.lockout_duration', 15);
            $this->update([
                'locked_until' => now()->addMinutes($lockoutMinutes),
            ]);
        }
    }

    /**
     * Reset failed login attempts
     */
    public function resetFailedLoginAttempts(): void
    {
        $this->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);
    }

    /**
     * Check if password was used recently (password history check)
     */
    public function hasUsedPasswordRecently(string $password): bool
    {
        if (!config('password.history.enabled')) {
            return false;
        }

        $historyCount = config('password.history.count', 5);

        $recentPasswords = $this->passwordHistories()
            ->orderBy('created_at', 'desc')
            ->limit($historyCount)
            ->get();

        foreach ($recentPasswords as $history) {
            if (password_verify($password, $history->password_hash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set password and update expiration
     */
    public function setPasswordWithExpiration(string $password): void
    {
        $expirationDays = config('password.expiration.days', 90);

        // Save current password to history before changing
        if (config('password.history.enabled') && $this->password) {
            PasswordHistory::create([
                'user_id' => $this->id,
                'password_hash' => $this->password,
                'created_at' => now(),
            ]);

            // Clean up old password history entries
            $historyCount = config('password.history.count', 5);

            // Get IDs of histories to keep (most recent ones)
            $keepIds = $this->passwordHistories()
                ->orderBy('created_at', 'desc')
                ->limit($historyCount)
                ->pluck('id')
                ->toArray();

            // Delete all histories for this user that are not in the keep list
            if (!empty($keepIds)) {
                PasswordHistory::where('user_id', $this->id)
                    ->whereNotIn('id', $keepIds)
                    ->delete();
            }
        }

        $this->update([
            'password' => bcrypt($password),
            'password_changed_at' => now(),
            'password_expires_at' => config('password.expiration.enabled')
                ? now()->addDays($expirationDays)
                : null,
            'force_password_change' => false,
        ]);
    }
}
