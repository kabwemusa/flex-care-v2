<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Fraud Rule Model
 *
 * Represents configurable fraud detection rules that can be
 * managed via admin interface without code changes.
 */
class FraudRule extends Model
{
    use HasUuids;

    protected $table = 'med_fraud_rules';

    protected $fillable = [
        'rule_code',
        'name',
        'description',
        'category',
        'severity',
        'points',
        'rule_logic',
        'is_active',
        'is_quick_check',
        'priority',
        'applies_to',
    ];

    protected $casts = [
        'rule_logic' => 'array',
        'is_active' => 'boolean',
        'is_quick_check' => 'boolean',
        'points' => 'integer',
        'priority' => 'integer',
        'applies_to' => 'array',
    ];

    /**
     * Categories for fraud rules
     */
    public const CATEGORIES = [
        'frequency' => 'Claim Frequency',
        'amount' => 'Claim Amount',
        'pattern' => 'Usage Patterns',
        'provider' => 'Provider Related',
        'member' => 'Member Related',
        'temporal' => 'Time Based',
        'document' => 'Document Related',
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
     * Entity types the rule can apply to
     */
    public const APPLIES_TO = [
        'claim' => 'Claims',
        'preauth' => 'Pre-authorizations',
        'both' => 'Both Claims and Pre-authorizations',
    ];

    /**
     * Boot method to clear cache on changes
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function () {
            Cache::forget('fraud_rules:active');
        });

        static::deleted(function () {
            Cache::forget('fraud_rules:active');
        });
    }

    /**
     * Scope to get active rules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get quick check rules
     */
    public function scopeQuickCheck($query)
    {
        return $query->where('is_quick_check', true);
    }

    /**
     * Scope by category
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Check if rule applies to claims
     */
    public function appliesToClaims(): bool
    {
        $appliesTo = $this->applies_to ?? ['both'];
        return in_array('claim', $appliesTo) || in_array('both', $appliesTo);
    }

    /**
     * Check if rule applies to pre-authorizations
     */
    public function appliesToPreAuth(): bool
    {
        $appliesTo = $this->applies_to ?? ['both'];
        return in_array('preauth', $appliesTo) || in_array('both', $appliesTo);
    }

    /**
     * Get threshold from rule logic
     */
    public function getThreshold(?string $key = 'threshold')
    {
        return $this->rule_logic[$key] ?? null;
    }
}
