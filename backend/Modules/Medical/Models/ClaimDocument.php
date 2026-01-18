<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Medical\Constants\MedicalConstants;

class ClaimDocument extends Model
{
    use HasUuids;

    protected $table = 'med_claim_documents';

    protected $fillable = [
        'claim_id',
        'document_type',
        'title',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'is_required',
        'is_verified',
        'verified_by',
        'verified_at',
        'uploaded_by',
        'upload_source',
        'is_active',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'file_size' => 'integer',
        'verified_at' => 'datetime',
    ];

    protected $attributes = [
        'is_required' => false,
        'is_verified' => false,
        'is_active' => true,
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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getDocumentTypeLabelAttribute(): string
    {
        return MedicalConstants::CLAIM_DOCUMENT_TYPES[$this->document_type] ?? $this->document_type;
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function verify(string $userId): void
    {
        $this->update([
            'is_verified' => true,
            'verified_by' => $userId,
            'verified_at' => now(),
        ]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }
}
