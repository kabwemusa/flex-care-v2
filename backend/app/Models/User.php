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
            'last_login_at' => 'datetime',
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
}
