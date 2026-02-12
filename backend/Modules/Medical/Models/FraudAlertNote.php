<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fraud Alert Note Model
 *
 * Tracks investigation notes and audit trail for fraud alerts.
 */
class FraudAlertNote extends Model
{
    use HasUuids;

    protected $table = 'med_fraud_alert_notes';

    protected $fillable = [
        'fraud_alert_id',
        'note_type',
        'content',
        'is_internal',
        'created_by_id',
        'created_by_type',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
    ];

    /**
     * Note types
     */
    public const NOTE_TYPES = [
        'system' => 'System Generated',
        'investigation' => 'Investigation Note',
        'resolution' => 'Resolution Note',
        'action' => 'Action Taken',
    ];

    /**
     * Relationship to fraud alert
     */
    public function fraudAlert(): BelongsTo
    {
        return $this->belongsTo(FraudAlert::class, 'fraud_alert_id');
    }

    /**
     * Get the user who created the note
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_id');
    }

    /**
     * Scope for internal notes only
     */
    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    /**
     * Scope by note type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('note_type', $type);
    }
}
