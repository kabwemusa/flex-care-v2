<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Medical\Constants\MedicalConstants;

class MemberDocument extends Model
{
    protected $table = 'med_member_documents';
    
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'member_id',
        'document_type',
        'title',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'issue_date',
        'expiry_date',
        'is_verified',
        'verified_by',
        'verified_at',
        'uploaded_by',
        'is_active',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_verified' => false,
        'is_active' => true,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Model $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::uuid()->toString();
            }
        });
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

    public function scopeByType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    public function scopeIdDocuments($query)
    {
        return $query->whereIn('document_type', [
            MedicalConstants::DOC_TYPE_ID_COPY,
            MedicalConstants::DOC_TYPE_PASSPORT,
        ]);
    }

    public function scopeMedicalDocuments($query)
    {
        return $query->where('document_type', MedicalConstants::DOC_TYPE_MEDICAL_REPORT);
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->whereNotNull('expiry_date')
                     ->where('expiry_date', '<=', now()->addDays($days))
                     ->where('expiry_date', '>=', now());
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getDocumentTypeLabelAttribute(): string
    {
        return MedicalConstants::MEMBER_DOCUMENT_TYPES[$this->document_type] ?? $this->document_type;
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        return $this->expiry_date 
            && $this->expiry_date->isFuture() 
            && $this->expiry_date->diffInDays(now()) <= 30;
    }

    public function getDaysToExpiryAttribute(): ?int
    {
        if (!$this->expiry_date) {
            return null;
        }

        return max(0, now()->diffInDays($this->expiry_date, false));
    }

    public function getFileExtensionAttribute(): string
    {
        return pathinfo($this->file_name, PATHINFO_EXTENSION);
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function verify(?string $verifiedBy = null): bool
    {
        $this->is_verified = true;
        $this->verified_by = $verifiedBy;
        $this->verified_at = now();
        
        return $this->save();
    }

    public function unverify(): bool
    {
        $this->is_verified = false;
        $this->verified_by = null;
        $this->verified_at = null;
        
        return $this->save();
    }

    public function deactivate(): bool
    {
        $this->is_active = false;
        
        return $this->save();
    }
}