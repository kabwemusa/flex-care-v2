<?php

namespace Modules\Medical\Models;

use Modules\Medical\Constants\MedicalConstants;

class LoadingRule extends BaseModel
{
    protected $table = 'med_loading_rules';

    protected $fillable = [
        'code',
        'condition_name',
        'condition_category',
        'icd10_code',
        'related_icd_codes',
        'loading_type',
        'loading_value',
        'min_loading',
        'max_loading',
        'duration_type',
        'duration_months',
        'exclusion_available',
        'exclusion_benefit_id',
        'exclusion_terms',
        'underwriting_notes',
        'required_documents',
        'assessment_criteria',
        'is_active',
    ];

    protected $casts = [
        'related_icd_codes' => 'array',
        'loading_value' => 'decimal:2',
        'min_loading' => 'decimal:2',
        'max_loading' => 'decimal:2',
        'duration_months' => 'integer',
        'exclusion_available' => 'boolean',
        'required_documents' => 'array',
        'assessment_criteria' => 'array',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'loading_type' => MedicalConstants::LOADING_TYPE_PERCENTAGE,
        'duration_type' => MedicalConstants::LOADING_DURATION_PERMANENT,
        'exclusion_available' => false,
        'is_active' => true,
    ];

    // =========================================================================
    // CODE GENERATION
    // =========================================================================

    protected function getCodePrefix(): string
    {
        return MedicalConstants::PREFIX_LOADING;
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    // No direct relationships - this is a catalog/reference table

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeByCategory($query, string $category)
    {
        return $query->where('condition_category', $category);
    }

    public function scopeChronic($query)
    {
        return $query->where('condition_category', MedicalConstants::CONDITION_CATEGORY_CHRONIC);
    }

    public function scopePreExisting($query)
    {
        return $query->where('condition_category', MedicalConstants::CONDITION_CATEGORY_PRE_EXISTING);
    }

    public function scopeLifestyle($query)
    {
        return $query->where('condition_category', MedicalConstants::CONDITION_CATEGORY_LIFESTYLE);
    }

    public function scopeByIcdCode($query, string $code)
    {
        return $query->where('icd10_code', $code)
                     ->orWhereJsonContains('related_icd_codes', $code);
    }

    public function scopePermanent($query)
    {
        return $query->where('duration_type', MedicalConstants::LOADING_DURATION_PERMANENT);
    }

    public function scopeTimeLimited($query)
    {
        return $query->where('duration_type', MedicalConstants::LOADING_DURATION_TIME_LIMITED);
    }

    public function scopeWithExclusionOption($query)
    {
        return $query->where('exclusion_available', true);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getConditionCategoryLabelAttribute(): string
    {
        return MedicalConstants::CONDITION_CATEGORIES[$this->condition_category] ?? $this->condition_category;
    }

    public function getLoadingTypeLabelAttribute(): string
    {
        return MedicalConstants::LOADING_TYPES[$this->loading_type] ?? $this->loading_type;
    }

    public function getDurationTypeLabelAttribute(): string
    {
        return MedicalConstants::LOADING_DURATIONS[$this->duration_type] ?? $this->duration_type;
    }

    public function getIsPercentageLoadingAttribute(): bool
    {
        return $this->loading_type === MedicalConstants::LOADING_TYPE_PERCENTAGE;
    }

    public function getIsFixedLoadingAttribute(): bool
    {
        return $this->loading_type === MedicalConstants::LOADING_TYPE_FIXED;
    }

    public function getIsExclusionTypeAttribute(): bool
    {
        return $this->loading_type === MedicalConstants::LOADING_TYPE_EXCLUSION;
    }

    public function getIsPermanentAttribute(): bool
    {
        return $this->duration_type === MedicalConstants::LOADING_DURATION_PERMANENT;
    }

    public function getIsTimeLimitedAttribute(): bool
    {
        return $this->duration_type === MedicalConstants::LOADING_DURATION_TIME_LIMITED;
    }

    public function getIsReviewableAttribute(): bool
    {
        return $this->duration_type === MedicalConstants::LOADING_DURATION_REVIEWABLE;
    }

    public function getFormattedLoadingValueAttribute(): string
    {
        if ($this->is_percentage_loading) {
            return $this->loading_value . '%';
        }

        if ($this->is_fixed_loading) {
            return 'K' . number_format($this->loading_value, 2);
        }

        return 'Exclusion';
    }

    public function getDurationLabelAttribute(): string
    {
        if ($this->is_permanent) {
            return 'Permanent';
        }

        if ($this->is_time_limited && $this->duration_months) {
            return $this->duration_months . ' months';
        }

        if ($this->is_reviewable) {
            return 'Annual review';
        }

        return 'N/A';
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function calculateLoading(float $premium): float
    {
        if ($this->is_exclusion_type) {
            return 0; // Exclusion doesn't add to premium
        }

        $loading = $this->is_percentage_loading
            ? round($premium * ($this->loading_value / 100), 2)
            : (float) $this->loading_value;

        // Apply min/max caps
        if ($this->min_loading !== null && $loading < $this->min_loading) {
            $loading = (float) $this->min_loading;
        }

        if ($this->max_loading !== null && $loading > $this->max_loading) {
            $loading = (float) $this->max_loading;
        }

        return $loading;
    }

    public function applyToPremium(float $premium): float
    {
        return $premium + $this->calculateLoading($premium);
    }

    public function getLoadingEndDate(\DateTimeInterface $startDate): ?\DateTimeInterface
    {
        if (!$this->is_time_limited || $this->duration_months === null) {
            return null;
        }

        // If it's already immutable, use modify directly
        if ($startDate instanceof \DateTimeImmutable) {
            return $startDate->modify("+{$this->duration_months} months");
        }

        // If it's a mutable DateTime, convert to DateTimeImmutable then modify
        if ($startDate instanceof \DateTime) {
            return \DateTimeImmutable::createFromMutable($startDate)->modify("+{$this->duration_months} months");
        }

        // Fallback: create a DateTimeImmutable from the formatted value (preserve timezone if available)
        $timezone = method_exists($startDate, 'getTimezone') ? $startDate->getTimezone() : null;
        $immutable = $timezone
            ? new \DateTimeImmutable($startDate->format('Y-m-d H:i:s'), $timezone)
            : new \DateTimeImmutable($startDate->format('Y-m-d H:i:s'));

        return $immutable->modify("+{$this->duration_months} months");
    }

    public function hasLoadingExpired(\DateTimeInterface $startDate): bool
    {
        $endDate = $this->getLoadingEndDate($startDate);

        if ($endDate === null) {
            return false; // Permanent or reviewable
        }

        return now()->greaterThan($endDate);
    }

    public function matchesIcdCode(string $code): bool
    {
        if ($this->icd10_code === $code) {
            return true;
        }

        if (!empty($this->related_icd_codes) && in_array($code, $this->related_icd_codes)) {
            return true;
        }

        return false;
    }

    public static function findByIcdCode(string $code): ?self
    {
        return static::active()
            ->where(function ($query) use ($code) {
                $query->where('icd10_code', $code)
                      ->orWhereJsonContains('related_icd_codes', $code);
            })
            ->first();
    }

    public static function findByConditionName(string $name): ?self
    {
        return static::active()
            ->where('condition_name', 'LIKE', "%{$name}%")
            ->first();
    }
}