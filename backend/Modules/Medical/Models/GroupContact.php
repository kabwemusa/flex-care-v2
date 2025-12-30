<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Medical\Constants\MedicalConstants;

class GroupContact extends BaseModel
{
    
    protected $table = 'med_group_contacts';
    
    // No softDeletes for contacts
    public $timestamps = true;

    protected $fillable = [
        'group_id',
        'contact_type',
        'first_name',
        'last_name',
        'job_title',
        'email',
        'phone',
        'mobile',
        'has_portal_access',
        'user_id',
        'permissions',
        'is_primary',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'permissions' => 'array',
        'has_portal_access' => 'boolean',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'has_portal_access' => false,
        'is_primary' => false,
        'is_active' => true,
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('contact_type', $type);
    }

    public function scopeWithPortalAccess($query)
    {
        return $query->where('has_portal_access', true);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getContactTypeLabelAttribute(): string
    {
        return MedicalConstants::CONTACT_TYPES[$this->contact_type] ?? $this->contact_type;
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function makePrimary(): bool
    {
        // Remove primary from other contacts
        static::where('group_id', $this->group_id)
              ->where('id', '!=', $this->id)
              ->update(['is_primary' => false]);

        $this->is_primary = true;
        
        return $this->save();
    }

    public function hasPermission(string $permission): bool
    {
        if (empty($this->permissions)) {
            return false;
        }

        return in_array($permission, $this->permissions);
    }

    public function grantPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];
        
        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->permissions = $permissions;
        }

        return $this->save();
    }

    public function revokePermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];
        $this->permissions = array_values(array_filter($permissions, fn($p) => $p !== $permission));

        return $this->save();
    }
}