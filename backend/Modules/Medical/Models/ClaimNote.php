<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Medical\Constants\MedicalConstants;

class ClaimNote extends Model
{
    use HasUuids;

    protected $table = 'med_claim_notes';

    protected $fillable = [
        'claim_id',
        'note_type',
        'content',
        'old_status',
        'new_status',
        'old_assignee',
        'new_assignee',
        'is_internal',
        'is_system',
        'created_by',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'is_system' => 'boolean',
    ];

    protected $attributes = [
        'is_internal' => true,
        'is_system' => false,
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    public function scopeExternal($query)
    {
        return $query->where('is_internal', false);
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    public function scopeManual($query)
    {
        return $query->where('is_system', false);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('note_type', $type);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getNoteTypeLabelAttribute(): string
    {
        return MedicalConstants::CLAIM_NOTE_TYPES[$this->note_type] ?? $this->note_type;
    }

    public function getOldStatusLabelAttribute(): ?string
    {
        if (!$this->old_status) return null;
        return MedicalConstants::CLAIM_STATUSES[$this->old_status] ?? $this->old_status;
    }

    public function getNewStatusLabelAttribute(): ?string
    {
        if (!$this->new_status) return null;
        return MedicalConstants::CLAIM_STATUSES[$this->new_status] ?? $this->new_status;
    }
}
