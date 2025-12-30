<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Medical\Constants\MedicalConstants;

class PolicyDocument extends Model
{
    protected $table = 'med_policy_documents';
    
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'policy_id',
        'document_type',
        'title',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'version',
        'issue_date',
        'valid_from',
        'valid_to',
        'uploaded_by',
        'generated_by',
        'is_system_generated',
        'is_active',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'issue_date' => 'date',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'is_system_generated' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'version' => '1.0',
        'is_system_generated' => false,
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

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class, 'policy_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    public function scopeCertificates($query)
    {
        return $query->where('document_type', MedicalConstants::DOC_TYPE_CERTIFICATE);
    }

    public function scopeSchedules($query)
    {
        return $query->where('document_type', MedicalConstants::DOC_TYPE_SCHEDULE);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getDocumentTypeLabelAttribute(): string
    {
        return MedicalConstants::POLICY_DOCUMENT_TYPES[$this->document_type] ?? $this->document_type;
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

    public function getIsValidAttribute(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $today = now()->toDateString();

        if ($this->valid_from && $this->valid_from > $today) {
            return false;
        }

        if ($this->valid_to && $this->valid_to < $today) {
            return false;
        }

        return true;
    }
}