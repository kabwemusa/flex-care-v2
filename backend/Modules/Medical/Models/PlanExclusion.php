<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Medical\Constants\MedicalConstants;

class PlanExclusion extends BaseModel
{
    protected $table = 'med_plan_exclusions';

    protected $fillable = [
        'plan_id',
        'benefit_id',
        'code',
        'name',
        'description',
        'exclusion_type',
        'conditions',
        'exclusion_period_days',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'exclusion_period_days' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'exclusion_type' => MedicalConstants::EXCLUSION_TYPE_ABSOLUTE,
        'sort_order' => 0,
        'is_active' => true,
    ];

    // =========================================================================
    // CODE GENERATION
    // =========================================================================

    protected function getCodePrefix(): string
    {
        return MedicalConstants::PREFIX_EXCLUSION;
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

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeAbsolute($query)
    {
        return $query->where('exclusion_type', MedicalConstants::EXCLUSION_TYPE_ABSOLUTE);
    }

    public function scopeConditional($query)
    {
        return $query->where('exclusion_type', MedicalConstants::EXCLUSION_TYPE_CONDITIONAL);
    }

    public function scopeTimeLimited($query)
    {
        return $query->where('exclusion_type', MedicalConstants::EXCLUSION_TYPE_TIME_LIMITED);
    }

    public function scopeGeneral($query)
    {
        return $query->whereNull('benefit_id');
    }

    public function scopeBenefitSpecific($query)
    {
        return $query->whereNotNull('benefit_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getExclusionTypeLabelAttribute(): string
    {
        return MedicalConstants::EXCLUSION_TYPES[$this->exclusion_type] ?? $this->exclusion_type;
    }

    public function getIsGeneralAttribute(): bool
    {
        return $this->benefit_id === null;
    }

    public function getIsBenefitSpecificAttribute(): bool
    {
        return $this->benefit_id !== null;
    }

    public function getIsTimeLimitedAttribute(): bool
    {
        return $this->exclusion_type === MedicalConstants::EXCLUSION_TYPE_TIME_LIMITED;
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function isExcludedForBenefit(string $benefitId): bool
    {
        // General exclusion applies to all
        if ($this->benefit_id === null) {
            return true;
        }

        return $this->benefit_id === $benefitId;
    }

    public function hasExclusionPeriodElapsed(\DateTimeInterface $coverStartDate): bool
    {
        if (!$this->is_time_limited || $this->exclusion_period_days === null) {
            return false;
        }

        // Create a DateTimeImmutable from the DateTimeInterface and modify it,
        // because DateTimeInterface does not define modify().
        $exclusionEndDate = \DateTimeImmutable::createFromInterface($coverStartDate)
            ->modify("+{$this->exclusion_period_days} days");

        return now()->greaterThanOrEqualTo($exclusionEndDate);
    }
}