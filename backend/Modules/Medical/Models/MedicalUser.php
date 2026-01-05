<?php

namespace Modules\Medical\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Traits\HasRoles;

class MedicalUser extends Model implements Auditable
{
    use HasUuids, SoftDeletes, HasRoles;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'medical_users';

    protected $guard_name = 'medical';

    protected $fillable = [
        'iam_user_id',
        'employee_number',
        'department',
        'hire_date',
        'job_title',
        'supervisor_id',
        'context_group_id',
        'is_active',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Link to global IAM user
     */
    public function iamUser()
    {
        return $this->belongsTo(User::class, 'iam_user_id');
    }

    /**
     * Supervisor relationship
     */
    public function supervisor()
    {
        return $this->belongsTo(MedicalUser::class, 'supervisor_id');
    }

    /**
     * Subordinates
     */
    public function subordinates()
    {
        return $this->hasMany(MedicalUser::class, 'supervisor_id');
    }

    /**
     * Context group (for corporate admin row-level security)
     */
    public function contextGroup()
    {
        return $this->belongsTo(CorporateGroup::class, 'context_group_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Active medical users only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Users with specific role
     */
    public function scopeWithRole($query, string $roleName)
    {
        return $query->whereHas('roles', function ($q) use ($roleName) {
            $q->where('name', $roleName)->where('guard_name', 'medical');
        });
    }

    /**
     * Users in specific department
     */
    public function scopeInDepartment($query, string $department)
    {
        return $query->where('department', $department);
    }

    /**
     * Users with context group (Corporate Admins)
     */
    public function scopeWithContext($query)
    {
        return $query->whereNotNull('context_group_id');
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Check if user has restricted context (can only access specific group)
     */
    public function hasRestrictedContext(): bool
    {
        return !is_null($this->context_group_id);
    }

    /**
     * Get the IAM user's email
     */
    public function getEmailAttribute(): ?string
    {
        return $this->iamUser?->email;
    }

    /**
     * Get full name (from IAM if available, or employee number)
     */
    public function getFullNameAttribute(): string
    {
        return $this->iamUser?->username ?? $this->employee_number ?? 'Unknown';
    }

    /**
     * Check if this medical user can access a specific group
     */
    public function canAccessGroup(string $groupId): bool
    {
        // If no context restriction, can access all groups
        if (!$this->hasRestrictedContext()) {
            return true;
        }

        // Otherwise, can only access their context group
        return $this->context_group_id === $groupId;
    }

    /**
     * Apply group context filter to query
     */
    public function applyGroupFilter($query, string $groupColumn = 'group_id')
    {
        if ($this->hasRestrictedContext()) {
            return $query->where($groupColumn, $this->context_group_id);
        }

        return $query;
    }
}
