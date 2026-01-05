<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Medical\Constants\MedicalConstants;

class AddonRate extends BaseModel
{
    protected $table = 'med_addon_rates';

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
        'addon_id',
        'plan_id',
        'pricing_type',
        'currency',
        'amount',
        'percentage',
        'percentage_basis',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'percentage' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'pricing_type' => MedicalConstants::ADDON_PRICING_FIXED,
        'currency' => MedicalConstants::DEFAULT_CURRENCY,
        'is_active' => false,
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

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class, 'addon_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(AddonRateEntry::class, 'addon_rate_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeEffectivee($query)
    {
        return $query->where('effective_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', now());
            });
    }

    public function scopeByPricingType($query, string $type)
    {
        return $query->where('pricing_type', $type);
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('plan_id');
    }

    public function scopeForPlan($query, string $planId)
    {
        return $query->where('plan_id', $planId);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getPricingTypeLabelAttribute(): string
    {
        return MedicalConstants::ADDON_PRICING_TYPES[$this->pricing_type] ?? $this->pricing_type;
    }

    public function getIsEffectiveAttribute(): bool
    {
        $today = now()->toDateString();
        
        return $this->effective_from <= $today 
            && ($this->effective_to === null || $this->effective_to >= $today);
    }

    public function getIsGlobalAttribute(): bool
    {
        return $this->plan_id === null;
    }

    public function getIsPlanSpecificAttribute(): bool
    {
        return $this->plan_id !== null;
    }

    public function getIsFixedAttribute(): bool
    {
        return $this->pricing_type === MedicalConstants::ADDON_PRICING_FIXED;
    }

    public function getIsPerMemberAttribute(): bool
    {
        return $this->pricing_type === MedicalConstants::ADDON_PRICING_PER_MEMBER;
    }

    public function getIsPercentageAttribute(): bool
    {
        return $this->pricing_type === MedicalConstants::ADDON_PRICING_PERCENTAGE;
    }

    public function getIsAgeRatedAttribute(): bool
    {
        return $this->pricing_type === MedicalConstants::ADDON_PRICING_AGE_RATED;
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function calculatePremium(
        int $memberCount = 1,
        float $basePremium = 0,
        ?int $age = null,
        ?string $gender = null
    ): float {
        return match($this->pricing_type) {
            MedicalConstants::ADDON_PRICING_FIXED => (float) $this->amount,
            MedicalConstants::ADDON_PRICING_PER_MEMBER => (float) ($this->amount * $memberCount),
            MedicalConstants::ADDON_PRICING_PERCENTAGE => round($basePremium * ($this->percentage / 100), 2),
            MedicalConstants::ADDON_PRICING_AGE_RATED => $this->getAgeRatedPremium($age, $gender),
            default => 0,
        };
    }

    public function getAgeRatedPremium(?int $age, ?string $gender = null): float
    {
        if ($age === null) {
            return 0;
        }

        $query = $this->entries()
            ->where('min_age', '<=', $age)
            ->where('max_age', '>=', $age);

        if ($gender !== null) {
            $query->where(function ($q) use ($gender) {
                $q->whereNull('gender')
                  ->orWhere('gender', $gender);
            });
        }

        $entry = $query->first();

        return (float) ($entry?->premium ?? 0);
    }
}