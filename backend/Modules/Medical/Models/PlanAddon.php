<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Medical\Constants\MedicalConstants;

class PlanAddon extends BaseModel
{
    protected $table = 'med_plan_addons';

    /**
     * Disable soft deletes for this model.
     */
    use \Illuminate\Database\Eloquent\SoftDeletes {
        \Illuminate\Database\Eloquent\SoftDeletes::bootSoftDeletes as parentBootSoftDeletes;
    }

    public static function bootSoftDeletes()
    {
        // Disable soft deletes for this model
    }

    protected $fillable = [
        'plan_id',
        'addon_id',
        'availability',
        'conditions',
        'benefit_overrides',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'conditions' => 'array',
        'benefit_overrides' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'availability' => MedicalConstants::ADDON_AVAILABILITY_OPTIONAL,
        'is_active' => true,
        'sort_order' => 0,
    ];

    // =========================================================================
    // CODE GENERATION (Not needed)
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

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class, 'addon_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeByAvailability($query, string $availability)
    {
        return $query->where('availability', $availability);
    }

    public function scopeMandatory($query)
    {
        return $query->where('availability', MedicalConstants::ADDON_AVAILABILITY_MANDATORY);
    }

    public function scopeOptional($query)
    {
        return $query->where('availability', MedicalConstants::ADDON_AVAILABILITY_OPTIONAL);
    }

    public function scopeIncluded($query)
    {
        return $query->where('availability', MedicalConstants::ADDON_AVAILABILITY_INCLUDED);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getAvailabilityLabelAttribute(): string
    {
        return MedicalConstants::ADDON_AVAILABILITIES[$this->availability] ?? $this->availability;
    }

    public function getIsMandatoryAttribute(): bool
    {
        return $this->availability === MedicalConstants::ADDON_AVAILABILITY_MANDATORY;
    }

    public function getIsOptionalAttribute(): bool
    {
        return $this->availability === MedicalConstants::ADDON_AVAILABILITY_OPTIONAL;
    }

    public function getIsIncludedAttribute(): bool
    {
        return $this->availability === MedicalConstants::ADDON_AVAILABILITY_INCLUDED;
    }

    public function getIsConditionalAttribute(): bool
    {
        return $this->availability === MedicalConstants::ADDON_AVAILABILITY_CONDITIONAL;
    }

    public function getRequiresAdditionalPremiumAttribute(): bool
    {
        // Included addons don't require additional premium
        return !$this->is_included;
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function getBenefitOverride(string $benefitId): ?array
    {
        if (empty($this->benefit_overrides)) {
            return null;
        }

        return $this->benefit_overrides[$benefitId] ?? null;
    }

    public function getEffectiveBenefitLimit(string $benefitId): ?float
    {
        $override = $this->getBenefitOverride($benefitId);
        
        if ($override && isset($override['limit_amount'])) {
            return (float) $override['limit_amount'];
        }

        // Get from addon_benefits
        $addonBenefit = $this->addon->addonBenefits()
            ->where('benefit_id', $benefitId)
            ->first();

        return $addonBenefit?->limit_amount;
    }

    public function meetsConditions(array $context = []): bool
    {
        if (!$this->is_conditional || empty($this->conditions)) {
            return true;
        }

        // Implement condition checking logic based on your rules
        // This is a simplified example
        foreach ($this->conditions as $key => $value) {
            if (!isset($context[$key]) || $context[$key] !== $value) {
                return false;
            }
        }

        return true;
    }
}