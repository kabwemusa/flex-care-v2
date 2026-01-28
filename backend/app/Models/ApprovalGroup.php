<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use OwenIt\Auditing\Contracts\Auditable;

class ApprovalGroup extends Model implements Auditable
{
    use HasFactory, HasUuids, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $hidden = ['memberships'];

    protected $appends = ['members'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get members formatted for API response (ApprovalGroupMember with user relation)
     */
    public function getMembersAttribute()
    {
        // If memberships relation is loaded, return it (this includes user data)
        if ($this->relationLoaded('memberships')) {
            return $this->memberships;
        }

        // If members relation is loaded, return it (User objects)
        if ($this->relationLoaded('members')) {
            return $this->getRelation('members');
        }

        // Return empty collection if neither relation is loaded
        return collect([]);
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the user who created this group
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this group
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the group memberships
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(ApprovalGroupMember::class, 'group_id');
    }

    /**
     * Get the users in this group
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'approval_group_members', 'group_id', 'user_id')
            ->withPivot('added_by', 'created_at')
            ->withTimestamps();
    }

    /**
     * Get the approval steps that use this group
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class, 'group_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope: Active groups only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Check if a user is a member of this group
     */
    public function hasMember(User $user): bool
    {
        // Use memberships to avoid ambiguous column reference
        return $this->memberships()->where('user_id', $user->id)->exists();
    }

    /**
     * Add a user to this group
     */
    public function addMember(User $user, ?User $addedBy = null): ApprovalGroupMember
    {
        return ApprovalGroupMember::firstOrCreate(
            [
                'group_id' => $this->id,
                'user_id' => $user->id,
            ],
            [
                'added_by' => $addedBy?->id,
            ]
        );
    }

    /**
     * Remove a user from this group
     */
    public function removeMember(User $user): bool
    {
        return $this->memberships()->where('user_id', $user->id)->delete() > 0;
    }

    /**
     * Get active members count
     */
    public function getActiveMembersCountAttribute(): int
    {
        return $this->members()->whereHas('', function ($q) {
            // We want active users
        })->where('is_active', true)->count();
    }
}
