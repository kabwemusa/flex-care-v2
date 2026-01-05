<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Modules\Medical\Constants\MedicalConstants;

class ApplicationDocument extends Model
{
    use HasUuids;

    protected $table = 'med_application_documents';

    protected $fillable = [
        'application_id',
        'application_member_id',
        'document_type',
        'title',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'is_verified',
        'verified_by',
        'verified_at',
        'uploaded_by',
        'is_active',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_verified' => false,
        'is_active' => true,
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(ApplicationMember::class, 'application_member_id');
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

    public function scopeUnverified($query)
    {
        return $query->where('is_verified', false);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    public function scopeForMember($query, string $memberId)
    {
        return $query->where('application_member_id', $memberId);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    // public function getDocumentTypeLabelAttribute(): string
    // {
    //     return MedicalConstants::DOCUMENT_TYPES[$this->document_type] ?? $this->document_type;
    // }

    public function getFileSizeFormattedAttribute(): string
    {
        if (!$this->file_size) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    public function getFileExtensionAttribute(): string
    {
        return pathinfo($this->file_name, PATHINFO_EXTENSION);
    }

    public function getIsForMemberAttribute(): bool
    {
        return $this->application_member_id !== null;
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    /**
     * Mark document as verified.
     */
    public function verify(string $verifiedBy): bool
    {
        $this->is_verified = true;
        $this->verified_by = $verifiedBy;
        $this->verified_at = now();
        
        return $this->save();
    }

    /**
     * Mark document as unverified.
     */
    public function unverify(): bool
    {
        $this->is_verified = false;
        $this->verified_by = null;
        $this->verified_at = null;
        
        return $this->save();
    }
}