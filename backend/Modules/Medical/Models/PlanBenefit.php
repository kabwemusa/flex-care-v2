<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Medical\Constants\MedicalConstants;

class PlanBenefit extends BaseModel
{
    protected $table = 'med_plan_benefits';

    protected $fillable = [
        'plan_id',
        'benefit_id',
        'parent_plan_benefit_id',
        'limit_type',
        'limit_frequency',
        'limit_basis',
        'limit_amount',
        'limit_count',
        'limit_days',
        'per_claim_limit',
        'per_day_limit',
        'max_claims_per_year',
        'waiting_period_days',
        'cost_sharing',
        'network_restriction',
        'is_covered',
        'is_visible',
        'display_value',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'limit_amount' => 'decimal:2',
        'limit_count' => 'integer',
        'limit_days' => 'integer',
        'per_claim_limit' => 'decimal:2',
        'per_day_limit' => 'decimal:2',
        'max_claims_per_year' => 'integer',
        'waiting_period_days' => 'integer',
        'cost_sharing' => 'array',
        'is_covered' => 'boolean',
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'is_covered' => true,
        'is_visible' => true,
        'sort_order' => 0,
    ];

    // =========================================================================
    // CODE GENERATION (Not needed - no code field)
    // =========================================================================

    protected function shouldGenerateCode(): bool
    {
        return false;
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function benefit(): BelongsTo
    {
        return $this->belongsTo(Benefit::class, 'benefit_id');
    }

    public function parentPlanBenefit(): BelongsTo
    {
        return $this->belongsTo(PlanBenefit::class, 'parent_plan_benefit_id');
    }

    public function childPlanBenefits(): HasMany
    {
        return $this->hasMany(PlanBenefit::class, 'parent_plan_benefit_id');
    }

    public function memberLimits(): HasMany
    {
        return $this->hasMany(PlanBenefitLimit::class, 'plan_benefit_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeCovered($query)
    {
        return $query->where('is_covered', true);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeRootLevel($query)
    {
        return $query->whereNull('parent_plan_benefit_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getEffectiveLimitTypeAttribute(): string
    {
        return $this->limit_type ?? $this->benefit->limit_type ?? MedicalConstants::MONETARY;
    }

    public function getEffectiveLimitFrequencyAttribute(): string
    {
        return $this->limit_frequency ?? $this->benefit->limit_frequency ?? MedicalConstants::PER_ANNUM;
    }

    public function getEffectiveLimitBasisAttribute(): string
    {
        return $this->limit_basis ?? $this->benefit->limit_basis ?? MedicalConstants::PER_MEMBER;
    }

    public function getIsSubLimitAttribute(): bool
    {
        return $this->parent_plan_benefit_id !== null;
    }

    public function getHasSubLimitsAttribute(): bool
    {
        return $this->childPlanBenefits()->exists();
    }

    public function getFormattedDisplayValueAttribute(): string
    {
        if ($this->display_value) {
            return $this->display_value;
        }

        return $this->formatLimit();
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function formatLimit(): string
    {
        $limitType = $this->effective_limit_type;
        
        return match($limitType) {
            MedicalConstants::MONETARY => $this->limit_amount 
                ? 'K' . number_format($this->limit_amount, 2) 
                : 'N/A',
            MedicalConstants::COUNT => $this->limit_count 
                ? $this->limit_count . ' visits' 
                : 'N/A',
            MedicalConstants::DAYS => $this->limit_days 
                ? $this->limit_days . ' days' 
                : 'N/A',
            MedicalConstants::UNLIMITED => 'Unlimited',
            default => 'N/A',
        };
    }

    public function getLimitForMemberType(string $memberType, int $age): ?PlanBenefitLimit
    {
        $query = $this->memberLimits()
            ->where('member_type', $memberType);

        if ($age !== null) {
            $query->where('min_age', '<=', $age)
                  ->where('max_age', '>=', $age);
        }

        return $query->first();
    }

    public function getEffectiveLimitAmount(string $memberType, int $age): ?float
    {
        // Check for member-specific limit first
        $memberLimit = $this->getLimitForMemberType($memberType, $age);
        
        if ($memberLimit && $memberLimit->limit_amount !== null) {
            return $memberLimit->limit_amount;
        }

        // Fall back to plan benefit limit
        return $this->limit_amount;
    }

    public function getEffectiveWaitingDays(): int
    {
        // Plan benefit override
        if ($this->waiting_period_days !== null) {
            return $this->waiting_period_days;
        }

        // Get from plan defaults based on benefit's waiting type
        $waitingType = $this->benefit->waiting_period_type ?? MedicalConstants::WAITING_TYPE_GENERAL;
        
        return $this->plan->getWaitingPeriodDays($waitingType);
    }

    public function getCostSharingConfig(): array
    {
        // Merge plan defaults with benefit-specific overrides
        $planDefaults = $this->plan->default_cost_sharing ?? [];
        $benefitOverrides = $this->cost_sharing ?? [];

        return array_merge($planDefaults, $benefitOverrides);
    }
}