<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fraud Pattern Model
 *
 * Stores detected patterns for ML training and analysis.
 * Patterns can be verified as fraud or legitimate for supervised learning.
 */
class FraudPattern extends Model
{
    use HasUuids;

    protected $table = 'med_fraud_patterns';

    protected $fillable = [
        'pattern_type',
        'member_id',
        'provider_id',
        'related_claims',
        'pattern_data',
        'period_start',
        'period_end',
        'claim_count',
        'total_amount',
        'anomaly_score',
        'model_version',
        'is_verified',
        'verified_as',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'related_claims' => 'array',
        'pattern_data' => 'array',
        'period_start' => 'date',
        'period_end' => 'date',
        'claim_count' => 'integer',
        'total_amount' => 'decimal:2',
        'anomaly_score' => 'integer',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /**
     * Pattern types
     */
    public const PATTERN_TYPES = [
        'claim_analysis' => 'Individual Claim Analysis',
        'frequency_anomaly' => 'Frequency Anomaly',
        'amount_anomaly' => 'Amount Anomaly',
        'provider_pattern' => 'Provider Pattern',
        'member_pattern' => 'Member Pattern',
        'network_pattern' => 'Network/Relationship Pattern',
    ];

    /**
     * Verification outcomes
     */
    public const VERIFIED_AS = [
        'fraud' => 'Confirmed Fraud',
        'legitimate' => 'Legitimate Activity',
        'inconclusive' => 'Inconclusive',
    ];

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
     * Scope for unverified patterns
     */
    public function scopeUnverified($query)
    {
        return $query->where('is_verified', false);
    }

    /**
     * Scope for verified patterns
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope for confirmed fraud patterns
     */
    public function scopeFraud($query)
    {
        return $query->where('is_verified', true)->where('verified_as', 'fraud');
    }

    /**
     * Scope for legitimate patterns
     */
    public function scopeLegitimate($query)
    {
        return $query->where('is_verified', true)->where('verified_as', 'legitimate');
    }

    /**
     * Scope by minimum anomaly score
     */
    public function scopeMinScore($query, int $score)
    {
        return $query->where('anomaly_score', '>=', $score);
    }

    /**
     * Scope by pattern type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('pattern_type', $type);
    }

    /**
     * Get training data export format
     */
    public function toTrainingData(): array
    {
        return [
            'pattern_type' => $this->pattern_type,
            'claim_count' => $this->claim_count,
            'total_amount' => (float) $this->total_amount,
            'anomaly_score' => $this->anomaly_score,
            'pattern_data' => $this->pattern_data,
            'is_fraud' => $this->verified_as === 'fraud',
        ];
    }
}
