<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Medical\Constants\MedicalConstants;

class DiscountRule extends BaseModel
{
    protected $table = 'med_discount_rules';

    protected $fillable = [
        'code',
        'name',
        'scheme_id',
        'plan_id',
        'adjustment_type',
        'value_type',
        'value',
        'applies_to',
        'application_method',
        'trigger_rules',
        'can_stack',
        'priority',
        'max_total_discount',
        'max_discount_amount',
        'usage_limit',
        'usage_count',
        'effective_from',
        'effective_to',
        'is_active',
        'description',
        'terms_conditions',
        'requires_approval',
        'approval_threshold',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'trigger_rules' => 'array',
        'can_stack' => 'boolean',
        'priority' => 'integer',
        'max_total_discount' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
        'requires_approval' => 'boolean',
        'approval_threshold' => 'decimal:2',
    ];

    protected $attributes = [
        'adjustment_type' => MedicalConstants::ADJUSTMENT_TYPE_DISCOUNT,
        'applies_to' => MedicalConstants::APPLIES_TO_TOTAL,
        'application_method' => MedicalConstants::APPLICATION_METHOD_AUTOMATIC,
        'can_stack' => true,
        'priority' => 0,
        'usage_count' => 0,
        'is_active' => true,
        'requires_approval' => false,
    ];

    // =========================================================================
    // CODE GENERATION
    // =========================================================================

    protected function getCodePrefix(): string
    {
        return MedicalConstants::PREFIX_DISCOUNT;
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class, 'scheme_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function promoCodes(): HasMany
    {
        return $this->hasMany(PromoCode::class, 'discount_rule_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeDiscounts($query)
    {
        return $query->where('adjustment_type', MedicalConstants::ADJUSTMENT_TYPE_DISCOUNT);
    }

    public function scopeLoadings($query)
    {
        return $query->where('adjustment_type', MedicalConstants::ADJUSTMENT_TYPE_LOADING);
    }

    public function scopeAutomatic($query)
    {
        return $query->where('application_method', MedicalConstants::APPLICATION_METHOD_AUTOMATIC);
    }

    public function scopeManual($query)
    {
        return $query->where('application_method', MedicalConstants::APPLICATION_METHOD_MANUAL);
    }

    public function scopePromoCode($query)
    {
        return $query->where('application_method', MedicalConstants::APPLICATION_METHOD_PROMO_CODE);
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('scheme_id')->whereNull('plan_id');
    }

    public function scopeForScheme($query, string $schemeId)
    {
        return $query->where(function ($q) use ($schemeId) {
            $q->whereNull('scheme_id')
              ->orWhere('scheme_id', $schemeId);
        });
    }

    public function scopeForPlan($query, string $planId)
    {
        return $query->where(function ($q) use ($planId) {
            $q->whereNull('plan_id')
              ->orWhere('plan_id', $planId);
        });
    }

    public function scopeStackable($query)
    {
        return $query->where('can_stack', true);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getAdjustmentTypeLabelAttribute(): string
    {
        return MedicalConstants::ADJUSTMENT_TYPES[$this->adjustment_type] ?? $this->adjustment_type;
    }

    public function getValueTypeLabelAttribute(): string
    {
        return MedicalConstants::VALUE_TYPES[$this->value_type] ?? $this->value_type;
    }

    public function getAppliesToLabelAttribute(): string
    {
        return MedicalConstants::APPLIES_TO_OPTIONS[$this->applies_to] ?? $this->applies_to;
    }

    public function getApplicationMethodLabelAttribute(): string
    {
        return MedicalConstants::APPLICATION_METHODS[$this->application_method] ?? $this->application_method;
    }

    public function getIsDiscountAttribute(): bool
    {
        return $this->adjustment_type === MedicalConstants::ADJUSTMENT_TYPE_DISCOUNT;
    }

    public function getIsLoadingAttribute(): bool
    {
        return $this->adjustment_type === MedicalConstants::ADJUSTMENT_TYPE_LOADING;
    }

    public function getIsPercentageAttribute(): bool
    {
        return $this->value_type === MedicalConstants::VALUE_TYPE_PERCENTAGE;
    }

    public function getIsFixedAttribute(): bool
    {
        return $this->value_type === MedicalConstants::VALUE_TYPE_FIXED;
    }

    public function getIsAutomaticAttribute(): bool
    {
        return $this->application_method === MedicalConstants::APPLICATION_METHOD_AUTOMATIC;
    }

    public function getIsManualAttribute(): bool
    {
        return $this->application_method === MedicalConstants::APPLICATION_METHOD_MANUAL;
    }

    public function getIsGlobalAttribute(): bool
    {
        return $this->scheme_id === null && $this->plan_id === null;
    }

    public function getHasUsageLimitAttribute(): bool
    {
        return $this->usage_limit !== null;
    }

    public function getIsUsageLimitReachedAttribute(): bool
    {
        if (!$this->has_usage_limit) {
            return false;
        }

        return $this->usage_count >= $this->usage_limit;
    }

    public function getFormattedValueAttribute(): string
    {
        if ($this->is_percentage) {
            return $this->value . '%';
        }

        return 'K' . number_format($this->value, 2);
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function calculateAdjustment(float $premium): float
    {
        $adjustment = $this->is_percentage
            ? round($premium * ($this->value / 100), 2)
            : (float) $this->value;

        // Apply max cap if set
        if ($this->max_discount_amount !== null && $adjustment > $this->max_discount_amount) {
            $adjustment = (float) $this->max_discount_amount;
        }

        return $adjustment;
    }

    public function applyToPremium(float $premium): float
    {
        $adjustment = $this->calculateAdjustment($premium);

        return $this->is_discount
            ? max(0, $premium - $adjustment)
            : $premium + $adjustment;
    }

    public function matchesTriggerRules(array $context): bool
    {
        if (empty($this->trigger_rules)) {
            return true;
        }

        foreach ($this->trigger_rules as $key => $expectedValue) {
            $actualValue = $context[$key] ?? null;

            // Handle different rule types
            if (str_ends_with($key, '_min')) {
                $actualKey = str_replace('_min', '', $key);
                if (($context[$actualKey] ?? 0) < $expectedValue) {
                    return false;
                }
            } elseif (str_ends_with($key, '_max')) {
                $actualKey = str_replace('_max', '', $key);
                if (($context[$actualKey] ?? 0) > $expectedValue) {
                    return false;
                }
            } elseif (is_array($expectedValue)) {
                if (!in_array($actualValue, $expectedValue)) {
                    return false;
                }
            } else {
                if ($actualValue !== $expectedValue) {
                    return false;
                }
            }
        }

        return true;
    }

    public function incrementUsage(): bool
    {
        $this->usage_count++;
        
        return $this->save();
    }

    public function canBeUsed(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if (!$this->is_effective) {
            return false;
        }

        if ($this->is_usage_limit_reached) {
            return false;
        }

        return true;
    }
}