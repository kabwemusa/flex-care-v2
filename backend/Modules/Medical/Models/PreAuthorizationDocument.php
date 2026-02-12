<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreAuthorizationDocument extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'med_preauthorization_documents';

    protected $fillable = [
        'preauthorization_id',
        'document_type',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'is_verified',
        'verified_by',
        'verified_at',
        'verification_notes',
        'uploaded_by',
        'upload_source',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function preauthorization(): BelongsTo
    {
        return $this->belongsTo(PreAuthorization::class, 'preauthorization_id');
    }

    public function getDocumentTypeLabelAttribute(): string
    {
        return match ($this->document_type) {
            'referral' => 'Referral Letter',
            'medical_report' => 'Medical Report',
            'lab_result' => 'Lab Result',
            'prescription' => 'Prescription',
            'other' => 'Other Document',
            default => ucfirst(str_replace('_', ' ', $this->document_type)),
        };
    }
}
