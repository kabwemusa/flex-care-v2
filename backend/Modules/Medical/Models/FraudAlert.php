<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fraud Alert Model
 *
 * Represents a fraud detection alert that requires investigation.
 */
class FraudAlert extends Model
{
    use HasUuids;

    protected $table = 'med_fraud_alerts';

    protected $fillable = [
        'alert_number',
        'entity_type',
        'entity_id',
        'claim_id',
        'preauth_id',
        'member_id',
        'provider_id',
        'fraud_score',
        'severity',
        'triggered_rules',
        'flags',
        'flagged_amount',
        'status',
        'assigned_to',
        'investigation_started_at',
        'resolution',
        'resolution_notes',
        'action_taken',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'triggered_rules' => 'array',
        'flags' => 'array',
        'flagged_amount' => 'decimal:2',
        'fraud_score' => 'integer',
        'investigation_started_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Alert statuses
     */
    public const STATUSES = [
        'open' => 'Open',
        'investigating' => 'Under Investigation',
        'confirmed_fraud' => 'Confirmed Fraud',
        'false_positive' => 'False Positive',
        'dismissed' => 'Dismissed',
    ];

    /**
     * Severity levels
     */
    public const SEVERITIES = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'critical' => 'Critical',
    ];

    /**
     * Resolution types
     */
    public const RESOLUTIONS = [
        'fraud_confirmed' => 'Fraud Confirmed',
        'false_positive' => 'False Positive',
        'inconclusive' => 'Inconclusive',
        'dismissed' => 'Dismissed',
    ];

    /**
     * Relationship to claim
     */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class, 'claim_id');
    }

    /**
     * Relationship to pre-authorization
     */
    public function preauthorization(): BelongsTo
    {
        return $this->belongsTo(PreAuthorization::class, 'preauth_id');
    }

    /**
     * Relationship to member
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * Relationship to provider
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    /**
     * Alert notes
     */
    public function notes(): HasMany
    {
        return $this->hasMany(FraudAlertNote::class, 'fraud_alert_id');
    }

    /**
     * Scope for open alerts
     */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'investigating']);
    }

    /**
     * Scope for high priority alerts
     */
    public function scopeHighPriority($query)
    {
        return $query->whereIn('severity', ['high', 'critical']);
    }

    /**
     * Scope by status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope by severity
     */
    public function scopeSeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Check if alert is open
     */
    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'investigating']);
    }

    /**
     * Check if alert is resolved
     */
    public function isResolved(): bool
    {
        return in_array($this->status, ['confirmed_fraud', 'false_positive', 'dismissed']);
    }

    /**
     * Get the entity being alerted on
     */
    public function getEntity(): Model|null
    {
        return match ($this->entity_type) {
            'claim' => $this->claim,
            'preauth' => $this->preauthorization,
            default => null,
        };
    }

    /**
     * Get human-readable severity
     */
    public function getSeverityLabel(): string
    {
        return self::SEVERITIES[$this->severity] ?? $this->severity;
    }

    /**
     * Get human-readable status
     */
    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
